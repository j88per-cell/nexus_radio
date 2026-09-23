<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ShowFields from './ShowFields.vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, reactive, computed } from 'vue';

const props = defineProps({
    shows:          Array,
    genres:         Array,
    freePlayPaused: Boolean,
});

// ── constants ─────────────────────────────────────────────────────────────────

const DAYS        = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const modeLabel   = { manual: 'Manual', auto: 'Auto' };
const statusColor = {
    draft:  'bg-zinc-700 text-zinc-300',
    active: 'bg-blue-900/60 text-blue-300',
    live:   'bg-green-900/60 text-green-300',
    done:   'bg-zinc-800 text-zinc-500',
};

// ── helpers ───────────────────────────────────────────────────────────────────

function fmtDuration(seconds) {
    if (!seconds) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h ? `${h}h ${m}m` : `${m}m`;
}

function fmtDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
}

function recurrenceLabel(show) {
    switch (show.recurrence) {
        case 'once':     return show.scheduled_at ? `Once · ${fmtDateTime(show.scheduled_at)}` : 'Once (unscheduled)';
        case 'interval': return `Every ${show.interval_hours}h`;
        case 'daily':    return `Daily · ${show.start_time}`;
        case 'weekly': {
            const days = (show.recurrence_days || []).map(d => DAYS[d]).join(', ');
            return `Weekly · ${days || '—'} ${show.start_time ?? ''}`;
        }
        default: return show.recurrence;
    }
}

// ── form factory ──────────────────────────────────────────────────────────────

function blankForm() {
    return {
        name:             '',
        description:      '',
        theme:            '',
        mode:             'auto',
        status:           'draft',
        priority:         0,
        recurrence:       'once',
        scheduled_at:     '',
        start_time:       '',
        duration_minutes: '',
        recurrence_days:  [],
        interval_hours:   12,
    };
}

// ── create ────────────────────────────────────────────────────────────────────

const showCreate = ref(false);
const createForm = useForm(blankForm());

function submitCreate() {
    createForm.post(route('admin.shows.store'), {
        onSuccess: () => { showCreate.value = false; createForm.reset(); Object.assign(createForm, blankForm()); },
    });
}

// ── edit ──────────────────────────────────────────────────────────────────────

const editId   = ref(null);
const editForm = useForm(blankForm());

function startEdit(show) {
    editId.value = show.id;
    Object.assign(editForm, {
        name:             show.name,
        description:      show.description ?? '',
        theme:            show.theme ?? '',
        mode:             show.mode,
        status:           show.status,
        priority:         show.priority,
        recurrence:       show.recurrence,
        scheduled_at:     show.scheduled_at ? show.scheduled_at.substring(0, 16) : '',
        start_time:       show.start_time ?? '',
        duration_minutes: show.duration_minutes ?? '',
        recurrence_days:  show.recurrence_days ?? [],
        interval_hours:   show.interval_hours ?? 12,
    });
    loadTracks(show.id);
    fetchArtists();
}

function submitEdit() {
    editForm.patch(route('admin.shows.update', editId.value), {
        onSuccess: () => { editId.value = null; showTracks.value = []; },
    });
}

function cancelEdit() {
    editId.value    = null;
    showTracks.value = [];
}

function deleteShow(show) {
    if (!confirm(`Delete "${show.name}"?`)) return;
    router.delete(route('admin.shows.destroy', show.id));
}

function startShow(show) {
    if (!confirm(`Start "${show.name}" now? Any currently live show will be stopped.`)) return;
    router.post(route('admin.shows.start', show.id));
}

function stopShow(show) {
    if (!confirm(`Stop "${show.name}"?`)) return;
    router.post(route('admin.shows.stop', show.id));
}

function toggleDay(form, day) {
    const idx = form.recurrence_days.indexOf(day);
    if (idx === -1) form.recurrence_days.push(day);
    else form.recurrence_days.splice(idx, 1);
}

// ── track picker (manual mode) ────────────────────────────────────────────────

const showTracks    = ref([]);
const loadingTracks = ref(false);

function loadTracks(showId) {
    loadingTracks.value = true;
    showTracks.value = [];
    fetch(route('admin.shows.tracks', showId))
        .then(r => r.json())
        .then(data => { showTracks.value = data; })
        .finally(() => { loadingTracks.value = false; });
}

// ── 3-column browser ──────────────────────────────────────────────────────────

const browser = reactive({
    filterQ:        '',
    filterGenre:    '',
    filterYearFrom: '',
    filterYearTo:   '',
    artists:        [],
    loadingArtists: false,
    selectedArtist: null,
    releases:       [],
    loadingReleases: false,
    selectedRelease: null,
    tracks:         [],
    loadingTracks:  false,
});

let artistTimer = null;

function fetchArtists() {
    browser.loadingArtists = true;
    const params = new URLSearchParams();
    if (browser.filterQ)        params.set('q',         browser.filterQ);
    if (browser.filterGenre)    params.set('genre',     browser.filterGenre);
    if (browser.filterYearFrom) params.set('year_from', browser.filterYearFrom);
    if (browser.filterYearTo)   params.set('year_to',   browser.filterYearTo);
    fetch(route('admin.shows.browse.artists') + '?' + params.toString())
        .then(r => r.json())
        .then(data => {
            browser.artists = data;
            // clear downstream if selected artist no longer in list
            if (browser.selectedArtist && !data.find(a => a.id === browser.selectedArtist.id)) {
                browser.selectedArtist  = null;
                browser.releases        = [];
                browser.selectedRelease = null;
                browser.tracks          = [];
            }
        })
        .finally(() => { browser.loadingArtists = false; });
}

function onFilterChange() {
    clearTimeout(artistTimer);
    artistTimer = setTimeout(fetchArtists, 300);
}

function selectArtist(artist) {
    browser.selectedArtist  = artist;
    browser.releases        = [];
    browser.selectedRelease = null;
    browser.tracks          = [];
    browser.loadingReleases = true;
    fetch(route('admin.shows.browse.releases') + '?artist_id=' + artist.id)
        .then(r => r.json())
        .then(data => { browser.releases = data; })
        .finally(() => { browser.loadingReleases = false; });
}

function selectRelease(release) {
    browser.selectedRelease = release;
    browser.tracks          = [];
    browser.loadingTracks   = true;
    fetch(route('admin.shows.browse.tracks') + '?release_id=' + release.id)
        .then(r => r.json())
        .then(data => { browser.tracks = data; })
        .finally(() => { browser.loadingTracks = false; });
}

function addTrack(track) {
    if (showTracks.value.find(t => t.track_id === track.id)) return;
    const artistName = browser.selectedArtist?.name  ?? '';
    const albumTitle = browser.selectedRelease?.title ?? '';
    showTracks.value.push({
        track_id:         track.id,
        position:         showTracks.value.length,
        duration_seconds: track.duration_seconds,
        song_title:       track.song_title,
        artist_name:      artistName,
        album_title:      albumTitle,
    });
}

function removeTrack(idx) {
    showTracks.value.splice(idx, 1);
    showTracks.value.forEach((t, i) => { t.position = i; });
}

function moveTrack(idx, dir) {
    const to = idx + dir;
    if (to < 0 || to >= showTracks.value.length) return;
    [showTracks.value[idx], showTracks.value[to]] = [showTracks.value[to], showTracks.value[idx]];
    showTracks.value.forEach((t, i) => { t.position = i; });
}

const totalDuration = computed(() => showTracks.value.reduce((s, t) => s + (t.duration_seconds || 0), 0));

const trackSaveStatus = ref(null); // null | 'saving' | 'saved' | 'error'

async function saveTracks() {
    trackSaveStatus.value = 'saving';
    const payload = showTracks.value.map((t, i) => ({
        track_id:         t.track_id,
        position:         i,
        duration_seconds: t.duration_seconds,
    }));
    try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.put(route('admin.shows.tracks.sync', editId.value), { tracks: payload });
        trackSaveStatus.value = 'saved';
        setTimeout(() => { trackSaveStatus.value = null; }, 3000);
    } catch {
        trackSaveStatus.value = 'error';
        setTimeout(() => { trackSaveStatus.value = null; }, 4000);
    }
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-white">Shows</h1>
                <div class="flex items-center gap-3">
                    <button v-if="freePlayPaused"
                        @click="router.post(route('admin.shows.free-play.resume'))"
                        class="px-3 py-1.5 bg-green-700 hover:bg-green-600 text-white text-sm rounded transition-colors">
                        Resume Free Play
                    </button>
                    <button v-else
                        @click="router.post(route('admin.shows.free-play.pause'))"
                        class="px-3 py-1.5 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-sm rounded transition-colors">
                        Pause Free Play
                    </button>
                    <button
                        @click="showCreate = !showCreate"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-sm rounded transition-colors"
                    >+ New Show</button>
                </div>
            </div>
        </template>

        <!-- ── Create form ─────────────────────────────────────────────────── -->
        <div v-if="showCreate" class="mb-6 bg-zinc-900 border border-zinc-700 rounded-lg p-5">
            <h2 class="text-base font-semibold text-white mb-4">New Show</h2>
            <form @submit.prevent="submitCreate" class="space-y-4">
                <ShowFields :form="createForm" />
                <div class="flex gap-3 pt-1">
                    <button type="submit" :disabled="createForm.processing"
                        class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm rounded transition-colors disabled:opacity-50">Save</button>
                    <button type="button" @click="showCreate = false; createForm.reset();"
                        class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white text-sm rounded transition-colors">Cancel</button>
                </div>
            </form>
        </div>

        <!-- ── Show list ───────────────────────────────────────────────────── -->
        <div class="space-y-3">
            <div v-for="show in shows" :key="show.id" class="bg-zinc-900 border border-zinc-800 rounded-lg">

                <!-- Summary row -->
                <div class="flex items-center gap-4 px-5 py-4">
                    <span class="text-xs font-mono text-zinc-500 w-5 text-right shrink-0">{{ show.priority }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-white">{{ show.name }}</span>
                            <span :class="['text-xs px-1.5 py-0.5 rounded', statusColor[show.status]]">{{ show.status }}</span>
                            <span class="text-xs text-zinc-400 bg-zinc-800 px-1.5 py-0.5 rounded">{{ modeLabel[show.mode] }}</span>
                            <span v-if="show.locked" class="text-xs text-amber-500 bg-amber-950/50 px-1.5 py-0.5 rounded">🔒 locked</span>
                        </div>
                        <div class="text-xs text-zinc-500 mt-1 flex flex-wrap gap-x-4">
                            <span>{{ recurrenceLabel(show) }}</span>
                            <span v-if="show.next_run_at">Next: {{ fmtDateTime(show.next_run_at) }}</span>
                            <span v-if="show.description" class="truncate max-w-xs">{{ show.description }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button v-if="show.status === 'live'" @click="stopShow(show)"
                            class="text-xs px-2 py-1 rounded transition-colors bg-amber-700 hover:bg-amber-600 text-white">Stop</button>
                        <button v-else-if="show.status === 'active' || show.status === 'draft'" @click="startShow(show)"
                            class="text-xs px-2 py-1 rounded transition-colors bg-green-700 hover:bg-green-600 text-white">Start</button>
                        <button @click="!show.locked && startEdit(show)"
                            :disabled="show.locked"
                            :class="['text-xs px-2 py-1 rounded transition-colors',
                                     show.locked
                                       ? 'text-zinc-600 cursor-not-allowed'
                                       : 'text-zinc-400 hover:text-white hover:bg-zinc-800']">Edit</button>
                        <button @click="deleteShow(show)"
                            class="text-xs text-red-500 hover:text-red-400 px-2 py-1 rounded hover:bg-zinc-800 transition-colors">Delete</button>
                    </div>
                </div>

                <!-- Edit panel -->
                <div v-if="editId === show.id" class="border-t border-zinc-800 px-5 py-5">
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <ShowFields :form="editForm" />
                        <div class="flex gap-3 pt-1">
                            <button type="submit" :disabled="editForm.processing"
                                class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm rounded transition-colors disabled:opacity-50">Save</button>
                            <button type="button" @click="cancelEdit"
                                class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white text-sm rounded transition-colors">Cancel</button>
                        </div>
                    </form>

                    <!-- Browser + Tracklist -->
                    <div class="mt-6 border-t border-zinc-800 pt-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-zinc-300">
                                {{ editForm.mode === 'manual' ? 'Tracklist' : 'Browse Library' }}
                                <span v-if="showTracks.length" class="text-zinc-500 font-normal ml-1">
                                    ({{ showTracks.length }} tracks · {{ fmtDuration(totalDuration) }})
                                </span>
                            </h3>
                            <button v-if="editForm.mode === 'manual'" type="button" @click="saveTracks"
                                :disabled="trackSaveStatus === 'saving'"
                                :class="[
                                    'text-xs px-2.5 py-1 rounded transition-colors',
                                    trackSaveStatus === 'saved'  ? 'bg-green-700 text-white' :
                                    trackSaveStatus === 'error'  ? 'bg-red-700 text-white' :
                                    trackSaveStatus === 'saving' ? 'bg-zinc-600 text-zinc-400 cursor-not-allowed' :
                                    'bg-blue-700 hover:bg-blue-600 text-white'
                                ]">
                                {{ trackSaveStatus === 'saving' ? 'Saving…' : trackSaveStatus === 'saved' ? 'Saved ✓' : trackSaveStatus === 'error' ? 'Error ✗' : 'Save Tracklist' }}
                            </button>
                        </div>

                        <!-- Browser filters -->
                        <div class="flex flex-wrap gap-2 mb-3">
                            <input v-model="browser.filterQ" @input="onFilterChange" type="text"
                                placeholder="Keyword (name, bio…)"
                                class="flex-1 min-w-32 bg-zinc-800 border border-zinc-700 rounded px-2 py-1.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-zinc-500" />
                            <select v-model="browser.filterGenre" @change="onFilterChange"
                                class="bg-zinc-800 border border-zinc-700 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-zinc-500">
                                <option value="">All genres</option>
                                <option v-for="g in genres" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                            <input v-model="browser.filterYearFrom" @input="onFilterChange" type="number"
                                placeholder="From year" min="1950" max="2099"
                                class="w-24 bg-zinc-800 border border-zinc-700 rounded px-2 py-1.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-zinc-500" />
                            <input v-model="browser.filterYearTo" @input="onFilterChange" type="number"
                                placeholder="To year" min="1950" max="2099"
                                class="w-24 bg-zinc-800 border border-zinc-700 rounded px-2 py-1.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-zinc-500" />
                        </div>

                        <!-- 3-column browser -->
                        <div class="grid grid-cols-3 gap-2 mb-4 h-52 border border-zinc-700 rounded overflow-hidden">
                            <!-- Artists -->
                            <div class="overflow-y-auto border-r border-zinc-700">
                                <div v-if="browser.loadingArtists" class="p-2 text-xs text-zinc-500">Loading…</div>
                                <div v-else-if="!browser.artists.length" class="p-2 text-xs text-zinc-500">No artists</div>
                                <button v-for="a in browser.artists" :key="a.id" type="button"
                                    @click="selectArtist(a)"
                                    :class="['w-full text-left px-2 py-1.5 text-xs truncate transition-colors',
                                             browser.selectedArtist?.id === a.id
                                               ? 'bg-red-900/50 text-white'
                                               : 'text-zinc-300 hover:bg-zinc-800']">
                                    {{ a.name }}
                                </button>
                            </div>

                            <!-- Albums -->
                            <div class="overflow-y-auto border-r border-zinc-700">
                                <div v-if="!browser.selectedArtist" class="p-2 text-xs text-zinc-500">← Pick an artist</div>
                                <div v-else-if="browser.loadingReleases" class="p-2 text-xs text-zinc-500">Loading…</div>
                                <div v-else-if="!browser.releases.length" class="p-2 text-xs text-zinc-500">No albums</div>
                                <button v-for="r in browser.releases" :key="r.id" type="button"
                                    @click="selectRelease(r)"
                                    :class="['w-full text-left px-2 py-1.5 text-xs transition-colors',
                                             browser.selectedRelease?.id === r.id
                                               ? 'bg-red-900/50 text-white'
                                               : 'text-zinc-300 hover:bg-zinc-800']">
                                    <span class="block truncate">{{ r.title }}</span>
                                    <span class="text-zinc-500">{{ r.year ?? '—' }}</span>
                                </button>
                            </div>

                            <!-- Tracks -->
                            <div class="overflow-y-auto">
                                <div v-if="!browser.selectedRelease" class="p-2 text-xs text-zinc-500">← Pick an album</div>
                                <div v-else-if="browser.loadingTracks" class="p-2 text-xs text-zinc-500">Loading…</div>
                                <div v-else-if="!browser.tracks.length" class="p-2 text-xs text-zinc-500">No tracks</div>
                                <button v-for="t in browser.tracks" :key="t.id" type="button"
                                    @click="editForm.mode === 'manual' && addTrack(t)"
                                    :class="['w-full text-left px-2 py-1.5 text-xs transition-colors',
                                             showTracks.find(st => st.track_id === t.id)
                                               ? 'text-zinc-600 cursor-default'
                                               : editForm.mode === 'manual'
                                                 ? 'text-zinc-300 hover:bg-zinc-800'
                                                 : 'text-zinc-300 cursor-default']">
                                    <span class="truncate block">
                                        <span class="text-zinc-500 mr-1">{{ t.position ?? '' }}</span>{{ t.song_title }}
                                    </span>
                                    <span class="text-zinc-500">{{ fmtDuration(t.duration_seconds) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Track rows -->
                        <p v-if="loadingTracks" class="text-sm text-zinc-500 py-2">Loading…</p>
                        <p v-else-if="!showTracks.length && editForm.mode === 'manual'" class="text-sm text-zinc-500 py-2">No tracks yet. Pick songs from the browser above.</p>
                        <div v-else class="space-y-1">
                            <div v-for="(t, i) in showTracks" :key="t.track_id"
                                class="flex items-center gap-2 text-sm bg-zinc-800/50 rounded px-3 py-2">
                                <span class="text-zinc-600 w-5 text-right text-xs shrink-0">{{ i + 1 }}</span>
                                <div class="flex-1 min-w-0 truncate">
                                    <span class="text-white">{{ t.song_title }}</span>
                                    <span class="text-zinc-400 ml-1">— {{ t.artist_name }}</span>
                                    <span class="text-zinc-500 text-xs ml-1">{{ t.album_title }}</span>
                                </div>
                                <span class="text-zinc-500 text-xs shrink-0">{{ fmtDuration(t.duration_seconds) }}</span>
                                <div v-if="editForm.mode === 'manual'" class="flex gap-1 shrink-0">
                                    <button type="button" @click="moveTrack(i, -1)" :disabled="i === 0"
                                        class="text-zinc-500 hover:text-zinc-300 disabled:opacity-30 w-6 text-center">↑</button>
                                    <button type="button" @click="moveTrack(i, 1)"  :disabled="i === showTracks.length - 1"
                                        class="text-zinc-500 hover:text-zinc-300 disabled:opacity-30 w-6 text-center">↓</button>
                                    <button type="button" @click="removeTrack(i)"
                                        class="text-red-500 hover:text-red-400 w-6 text-center">×</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="!shows.length" class="text-zinc-500 text-sm py-10 text-center">No shows yet. Create one above.</p>
        </div>
    </AdminLayout>
</template>
