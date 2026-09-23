#!/usr/bin/env python3
"""Extract acoustic features from an audio file, print JSON to stdout.

Usage: extract.py <path-to-audio-file>
"""
import json
import os
import shutil
import sys
import tempfile

import librosa
import numpy as np

PITCH_CLASSES = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"]


def detect_key(y, sr):
    chroma = librosa.feature.chroma_cqt(y=y, sr=sr)
    return PITCH_CLASSES[int(np.argmax(chroma.mean(axis=1)))]


def fmt_time(t):
    m = int(t // 60)
    s = t - m * 60
    return f"{m:d}:{s:04.1f}"


def is_tempo_unstable(seg_tempo, global_tempo, tol=0.15):
    """A segment tempo far from the global tempo (or a clean 0.5x/2x/3x
    multiple of it) usually means the beat tracker couldn't lock a real
    pulse there — a floating/suspended passage, not real instability."""
    if global_tempo <= 0 or not np.isfinite(seg_tempo):
        return False
    ratio = seg_tempo / global_tempo
    return not any(abs(ratio - m) <= tol * m for m in (0.5, 1.0, 2.0, 3.0))


def extract_shape_note(path, y, sr, global_tempo):
    """Deterministic, templated structural "shape note" via MSAF boundary
    detection + duration-weighted RMS/tempo per segment. See
    ai-memory/tools/song_shape_note.py for the original standalone version
    and the reasoning behind duration-weighting (an unweighted min/max over
    segments picks brief transients over the real sustained passage).
    Returns (note_text, meta_dict) — never raises; a failure here should
    never block the rest of the feature extraction (caller wraps in try/except).

    MSAF assumes a dataset layout — FileStruct derives its cache dir as
    dirname(dirname(audio_file)), expecting `<dataset>/audio/<file>` — and
    tries to create `<dataset>/estimations/` and `<dataset>/features/` there.
    A flat/temp file path breaks that (production's ffmpeg output under
    storage/app/tmp/ resolved to a non-writable, sometimes nonsensical path —
    one flat /tmp path even resolved to '/estimations'). So: copy the file
    into a fresh throwaway `<tmpdir>/audio/<file>` we own, let MSAF cache
    there, then delete the whole tmpdir — isolates every call and needs no
    MSAF config. A symlink here (instead of a real copy) reliably made MSAF
    raise "Couldn't find audio file" from inside its own feature-writing
    path (confirmed live) — copy it for real instead.
    """
    import msaf

    # Scratch dir lives next to the input file rather than in system /tmp —
    # for the real job that's storage/app/tmp, already proven writable by
    # www-data (ffmpeg writes the input file there), rather than depending
    # on whatever /tmp happens to allow.
    tmp_dir = tempfile.mkdtemp(prefix="msaf_", dir=os.path.dirname(os.path.abspath(path)))
    try:
        audio_dir = os.path.join(tmp_dir, "audio")
        os.makedirs(audio_dir, exist_ok=True)
        copied_path = os.path.join(audio_dir, os.path.basename(path))
        shutil.copyfile(path, copied_path)

        # Separately from the ds_path issue above: msaf.run.process() also
        # writes a features cache at msaf.config.features_tmp_file, which
        # defaults to a *relative* path resolved against the process's
        # current working directory — not the audio file's location at all.
        # Under a Laravel queue worker that CWD is the app root, which the
        # web user can't write to (confirmed live: FileNotFoundError trying
        # to open '.features_msaf_tmp.json'). Point it into our own tmp_dir.
        msaf.config.features_tmp_file = os.path.join(tmp_dir, ".features_msaf_tmp.json")

        boundaries, _ = msaf.process(copied_path, boundaries_id="sf", labels_id=None, feature="pcp")
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)

    duration = librosa.get_duration(y=y, sr=sr)

    bounds = list(boundaries)
    if bounds[0] > 0:
        bounds = [0.0] + bounds
    if bounds[-1] < duration:
        bounds = bounds + [duration]

    segments = []
    for i in range(len(bounds) - 1):
        t0, t1 = bounds[i], bounds[i + 1]
        i0, i1 = int(t0 * sr), int(t1 * sr)
        seg = y[i0:i1]
        if len(seg) < sr * 0.5:
            continue
        seg_rms = float(np.sqrt(np.mean(seg ** 2)))
        try:
            seg_tempo, _ = librosa.beat.beat_track(y=seg, sr=sr)
            seg_tempo = float(np.atleast_1d(seg_tempo)[0])
        except Exception:
            seg_tempo = float("nan")
        segments.append({"start": t0, "end": t1, "rms": seg_rms, "tempo": seg_tempo})

    if len(segments) < 2:
        return "Stays consistent throughout, no strong structural shifts detected.", {"segments": segments}

    durations = np.array([s["end"] - s["start"] for s in segments])
    rms_vals = np.array([s["rms"] for s in segments])
    total_dur = float(durations.sum())
    mean_rms = float(np.sum(rms_vals * durations) / total_dur)
    std_rms = float(np.sqrt(np.sum(durations * (rms_vals - mean_rms) ** 2) / total_dur))

    FLAT_CV = 0.22
    cv = std_rms / mean_rms if mean_rms > 0 else 0.0

    MIN_SEG_DUR = 8.0
    candidates = [s for s in segments if (s["end"] - s["start"]) >= MIN_SEG_DUR] or segments

    below = [s for s in candidates if s["rms"] < mean_rms - 0.3 * std_rms]
    above = [s for s in candidates if s["rms"] > mean_rms + 0.3 * std_rms]

    trough = max(below, key=lambda s: s["end"] - s["start"]) if below else min(candidates, key=lambda s: s["rms"])
    peak = max(above, key=lambda s: s["rms"]) if above else max(candidates, key=lambda s: s["rms"])

    meta = {
        "mean_rms": mean_rms, "cv": cv, "flat": cv < FLAT_CV,
        "trough": {"start": trough["start"], "end": trough["end"], "rms": trough["rms"]},
        "peak": {"start": peak["start"], "end": peak["end"], "rms": peak["rms"]},
        "n_segments": len(segments),
    }

    if cv < FLAT_CV:
        note = "Stays at a steady, high-intensity level throughout, with no major dynamic swings once it gets going."
        return note, meta

    trough_unstable = is_tempo_unstable(trough["tempo"], global_tempo)
    trough_time, peak_time = fmt_time(trough["start"]), fmt_time(peak["start"])

    if trough["start"] < peak["start"]:
        note = (f"Dips into a hushed passage around {trough_time} before climbing "
                f"to its most intense moment around {peak_time}.")
        if trough_unstable:
            note += " The quiet passage loses a fixed, steady beat entirely — it feels suspended, floating outside the song's normal pulse."
    else:
        note = (f"Opens at its most intense around {peak_time} and settles into "
                f"a quieter close around {trough_time}.")
        if trough_unstable:
            note += " That closing passage drifts away from a fixed beat rather than resolving cleanly."

    return note, meta


def main():
    if len(sys.argv) != 2:
        print("usage: extract.py <path-to-audio-file>", file=sys.stderr)
        sys.exit(1)

    path = sys.argv[1]
    y, sr = librosa.load(path, sr=None, mono=True)

    tempo, _ = librosa.beat.beat_track(y=y, sr=sr)
    tempo_bpm = float(np.asarray(tempo).item())
    rms = librosa.feature.rms(y=y)

    result = {
        "tempo_bpm": round(tempo_bpm, 2),
        "key": detect_key(y, sr),
        "energy": round(float(rms.mean()), 4),
        "shape_note": None,
        "shape_note_meta": None,
    }

    # Shape note is best-effort — never let it fail the tempo/key/energy
    # extraction the rest of the pipeline already depends on.
    try:
        note, meta = extract_shape_note(path, y, sr, tempo_bpm)
        result["shape_note"] = note
        result["shape_note_meta"] = meta
    except Exception:
        import traceback
        print("shape_note extraction failed:\n" + traceback.format_exc(), file=sys.stderr)

    print(json.dumps(result))


if __name__ == "__main__":
    main()
