# Settings
icecast_host = "127.0.0.1"
icecast_port = 12000
icecast_pass = "passw0rd"
control_url  = "http://nexus-radio.home"

# Station identity — override via env for your own deployment.
station_name        = getenv(default="Nexus Radio", "STATION_NAME")
station_description = getenv(default="Broadcasting from the void", "STATION_DESCRIPTION")
station_genre        = getenv(default="", "STATION_GENRE")

# Live DJ mic input (manual DJ mode) — a DJ connects an Icecast-source-style
# client (Mixxx, BUTT, OBS, etc.) directly to this harbor port/mount, not to
# Icecast itself. Laravel never touches the audio; it only sends mute/unmute
# over the telnet control channel below.
harbor_port     = int_of_string(default=8001, getenv(default="8001", "LIQUIDSOAP_HARBOR_PORT"))
harbor_password = getenv(default="passw0rd", "LIQUIDSOAP_HARBOR_PASSWORD")
harbor_mount    = getenv(default="live", "LIQUIDSOAP_HARBOR_MOUNT")

settings.server.telnet.set(true)
settings.server.telnet.port.set(1234)

# Apply replaygain_track_gain metadata injected by Laravel per-track
enable_replaygain_metadata()

# Pull next track from Laravel on demand — handles songs and DJ wav files.
# Bounded with a timeout + try/catch: an unhandled error or an unbounded
# http.get here can wedge this source forever with no further log output —
# every future fetch attempt just silently never happens, which is what
# "dead air, queue is full, logs show nothing" looks like from outside.
radio = request.dynamic(prefetch=1, fun() ->
  begin
    def fetch() =
      response = http.get(timeout=5.0, "#{control_url}/api/radio/next")
      url = string.trim(response)
      if url == "" then
        log("radio: queue empty, waiting...")
        null()
      else
        req = request.create(url)
        # The 5s timeout above only bounds the /next control call. Resolving
        # the request itself — actually fetching/decoding the audio at `url`
        # — has no such bound by default and can hang indefinitely (e.g. a
        # slow/unresponsive Navidrome), which is the exact "dead air, queue
        # is full, logs show nothing" failure this file already warns about
        # above. Resolve explicitly with a real timeout so a stuck fetch
        # fails fast and falls back to "queue empty, waiting" instead of
        # wedging the source.
        if request.resolve(req, timeout=10.) then
          log("radio: next = #{url}")
          req
        else
          log("radio: next fetch failed to resolve within timeout, skipping: #{url}")
          request.destroy(req)
          null()
        end
      end
    end
    try
      fetch()
    catch err do
      log("radio: /next fetch failed (#{error.kind(err)}): #{error.message(err)}")
      null()
    end
  end
)

radio = mksafe(radio)

# Notify Laravel when a track starts playing. Same hang risk as fetch()
# above: an unbounded http.post here blocks the track-advance path itself,
# not just this one webhook, so it needs the same timeout + try/catch.
radio = source.on_track(radio, fun(meta) ->
  ignore(
    try
      http.post(
        "#{control_url}/api/webhook/track-started",
        timeout=5.0,
        headers=[("Content-Type", "application/json")],
        data=json.stringify(meta)
      )
    catch err do
      log("radio: track-started webhook failed (#{error.kind(err)}): #{error.message(err)}")
      ""
    end
  )
)

# Detect stall/recovery, kick the source, and tell Laravel — before this,
# the 2026-08-26 dead-air incident had no record anywhere except a person
# happening to be awake and listening, and no recovery beyond whatever
# eventually unstuck itself on its own. Reports a transition only (not
# every poll), so consecutive events always alternate stalled/recovered.
radio_was_ready = ref(true)
stall_polls     = ref(0)

# 2 consecutive stalled polls at the 10s interval below = kick after ~20s
# of continuous not-ready, rather than on the very first blip.
kick_after_polls = 2

def post_health_event(event) =
  ignore(
    try
      http.post(
        "#{control_url}/api/webhook/radio-health",
        timeout=5.0,
        headers=[("Content-Type", "application/json")],
        data=json.stringify([("event", event)])
      )
    catch err do
      log("radio: health webhook failed (#{error.kind(err)}): #{error.message(err)}")
      ""
    end
  )
end

def check_radio_health() =
  ready = radio.is_ready()
  if ready then
    stall_polls := 0
    if not radio_was_ready() then
      radio_was_ready := true
      log("radio: health check — recovered")
      post_health_event("recovered")
    end
  else
    stall_polls := stall_polls() + 1
    if radio_was_ready() then
      radio_was_ready := false
      log("radio: health check — stalled")
      post_health_event("stalled")
    end
    if stall_polls() == kick_after_polls then
      log("radio: still stalled after #{kick_after_polls} polls, kicking source")
      source.skip(radio)
    end
  end
end

thread.run(every={10.}, check_radio_health)

# ── Live DJ mic input ───────────────────────────────────────────────────────
# `mic_muted` is a telnet-controllable variable (var.get/var.set over the
# telnet port above) — LiquidsoapService::command() drives it from Laravel's
# mute/unmute buttons without ever touching the harbor connection itself, so
# a DJ can mute between announcements without dropping their source.
mic_muted = interactive.bool("mic_muted", false)

mic_input = input.harbor(
  id=harbor_mount,
  harbor_mount,
  port=harbor_port,
  password=harbor_password,
  buffer=2.,
  timeout=10.
)
mic_input = mksafe(mic_input)

# The mic takes over the stream only while actually connected AND unmuted;
# otherwise the normal track queue keeps playing. First matching predicate
# wins, evaluated continuously — no manual source-switching needed.
on_air = switch(track_sensitive=false, [
  ({source.is_ready(mic_input) and not mic_muted()}, mic_input),
  ({true}, radio),
])

output.icecast(
  %mp3(bitrate=192),
  host=icecast_host,
  port=icecast_port,
  password=icecast_pass,
  mount="/stream",
  name=station_name,
  description=station_description,
  genre=station_genre,
  on_air
)

output.icecast(
  %mp3(bitrate=96),
  host=icecast_host,
  port=icecast_port,
  password=icecast_pass,
  mount="/stream-mobile",
  name="#{station_name} (Mobile)",
  description="#{station_description} — low bitrate",
  genre=station_genre,
  on_air
)
