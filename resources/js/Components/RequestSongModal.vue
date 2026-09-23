<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const query   = ref('');
const results = ref([]);
const searching = ref(false);
let searchTimer = null;

const selectedTrack = ref(null);

const form = useForm({
    track_id:     null,
    requested_by: '',
});

function onQueryInput() {
    clearTimeout(searchTimer);
    if (query.value.trim().length < 2) {
        results.value = [];
        return;
    }
    searchTimer = setTimeout(runSearch, 250);
}

function runSearch() {
    searching.value = true;
    fetch(route('admin.shows.search-tracks') + '?q=' + encodeURIComponent(query.value.trim()))
        .then(r => r.json())
        .then(data => { results.value = data; })
        .finally(() => { searching.value = false; });
}

function pickTrack(track) {
    selectedTrack.value = track;
    form.track_id = track.id;
    results.value = [];
    query.value = '';
}

function clearSelection() {
    selectedTrack.value = null;
    form.track_id = null;
}

function submit() {
    form.post(route('admin.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            reset();
            emit('close');
        },
    });
}

function reset() {
    form.reset();
    form.clearErrors();
    selectedTrack.value = null;
    query.value = '';
    results.value = [];
}

function close() {
    reset();
    emit('close');
}

watch(() => props.show, (isShown) => {
    if (isShown) reset();
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-center px-4 py-10 overflow-y-auto" @click.self="close">
        <div class="absolute inset-0 bg-black/70" @click="close" />

        <div class="relative w-full max-w-md bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <span class="font-mono text-[10.5px] tracking-[.14em] uppercase text-zinc-400 font-medium">Request a Song</span>
                <button @click="close" class="text-zinc-500 hover:text-zinc-300 text-lg leading-none">&times;</button>
            </div>

            <div class="p-5 space-y-4">
                <!-- Song picker -->
                <div v-if="!selectedTrack" class="space-y-2">
                    <label class="block font-mono text-[10px] tracking-[.1em] uppercase text-zinc-500">Song</label>
                    <input
                        v-model="query"
                        @input="onQueryInput"
                        type="text"
                        placeholder="Search title, artist, or album…"
                        class="w-full bg-zinc-950 border border-zinc-700 rounded-md px-3 py-2 text-[13px] text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-zinc-500"
                    />
                    <div v-if="searching" class="text-[11px] text-zinc-600">Searching…</div>
                    <div v-else-if="results.length" class="max-h-56 overflow-y-auto border border-zinc-800 rounded-md divide-y divide-zinc-800">
                        <button
                            v-for="t in results"
                            :key="t.id"
                            type="button"
                            @click="pickTrack(t)"
                            class="w-full text-left px-3 py-2 hover:bg-zinc-800 transition-colors"
                        >
                            <div class="text-[13px] text-zinc-200 truncate">{{ t.song_title }}</div>
                            <div class="text-[11px] text-zinc-500 truncate">{{ t.artist_name }} <span v-if="t.album_title">· {{ t.album_title }}</span></div>
                        </button>
                    </div>
                    <div v-else-if="query.trim().length >= 2 && !searching" class="text-[11px] text-zinc-600">We don't have that one — try another spelling or pick something else.</div>
                </div>

                <!-- Selected track -->
                <div v-else class="flex items-start justify-between gap-3 bg-zinc-950 border border-zinc-800 rounded-md px-3 py-2.5">
                    <div class="min-w-0">
                        <div class="text-[13px] text-zinc-200 truncate">{{ selectedTrack.song_title }}</div>
                        <div class="text-[11px] text-zinc-500 truncate">{{ selectedTrack.artist_name }} <span v-if="selectedTrack.album_title">· {{ selectedTrack.album_title }}</span></div>
                    </div>
                    <button @click="clearSelection" type="button" class="text-[11px] text-zinc-500 hover:text-zinc-300 shrink-0">change</button>
                </div>
                <p v-if="form.errors.track_id" class="text-[11px] text-rose-400">{{ form.errors.track_id }}</p>

                <!-- Requested by -->
                <div class="space-y-1.5">
                    <label class="block font-mono text-[10px] tracking-[.1em] uppercase text-zinc-500">Requested by</label>
                    <input
                        v-model="form.requested_by"
                        type="text"
                        maxlength="100"
                        placeholder="Name to credit"
                        class="w-full bg-zinc-950 border border-zinc-700 rounded-md px-3 py-2 text-[13px] text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-zinc-500"
                    />
                    <p v-if="form.errors.requested_by" class="text-[11px] text-rose-400">{{ form.errors.requested_by }}</p>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button
                        @click="submit"
                        :disabled="!form.track_id || !form.requested_by.trim() || form.processing"
                        class="ml-auto px-4 py-1.5 rounded bg-brass/90 hover:bg-brass text-zinc-950 font-semibold text-[12px] tracking-[.06em] transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    >Queue it</button>
                </div>
            </div>
        </div>
    </div>
</template>
