<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    songs:   Object,
    filters: Object,
    allTags: Array,
});

// ── Filters ─────────────────────────────────────────────────────────────────

const search = ref(props.filters?.search ?? '');
const artist = ref(props.filters?.artist ?? '');
const filter = ref(props.filters?.filter ?? '');

let searchTimer = null;

function applyFilters() {
    router.get('/admin/songs', {
        search: search.value || undefined,
        artist: artist.value || undefined,
        filter: filter.value || undefined,
    }, { preserveState: true, replace: true });
}

watch([search, artist, filter], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

// ── Details editing (lyrics, duration, tags) ──────────────────────────────────

const editingId  = ref(null);
const detailsForm = useForm({ lyrics: '', duration: '', tag: '' });

function secondsToClock(seconds) {
    if (!seconds && seconds !== 0) return '';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function clockToSeconds(clock) {
    if (!clock) return null;
    const parts = String(clock).trim().split(':');
    if (parts.length !== 2) return null;
    const m = parseInt(parts[0], 10);
    const s = parseInt(parts[1], 10);
    if (Number.isNaN(m) || Number.isNaN(s)) return null;
    return (m * 60) + s;
}

function openEdit(song) {
    editingId.value      = song.id;
    detailsForm.lyrics   = song.lyrics ?? '';
    detailsForm.duration = secondsToClock(song.duration_seconds);
    detailsForm.tag      = song.tags?.[0] ?? '';
}

function cancelEdit() {
    editingId.value = null;
    detailsForm.reset();
}

function saveDetails(song) {
    detailsForm.transform((data) => ({
        lyrics:            data.lyrics,
        tags:              data.tag ? [data.tag] : [],
        duration_seconds:  clockToSeconds(data.duration),
    })).patch(`/admin/songs/${song.id}`, {
        onSuccess: () => { editingId.value = null; },
    });
}

function tagLabel(tag) {
    return tag.replace(/_/g, ' ');
}

// ── Block toggle ─────────────────────────────────────────────────────────────

function toggleBlocked(song) {
    router.post(`/admin/songs/${song.id}/toggle-blocked`, {}, { preserveState: true });
}

</script>

<template>
    <Head title="Songs" />
    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-zinc-100">Songs</h1>
        </template>

        <div class="space-y-4">

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search title…"
                    class="bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-200 placeholder-zinc-500 focus:outline-none focus:border-zinc-500 w-48"
                />
                <input
                    v-model="artist"
                    type="text"
                    placeholder="Artist…"
                    class="bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-200 placeholder-zinc-500 focus:outline-none focus:border-zinc-500 w-40"
                />
                <select
                    v-model="filter"
                    class="bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-300 focus:outline-none focus:border-zinc-500"
                >
                    <option value="">All songs</option>
                    <option value="has_lyrics">Has lyrics</option>
                    <option value="no_lyrics">Missing lyrics</option>
                    <option value="has_shape_note">Has audio analysis</option>
                    <option value="no_shape_note">Missing audio analysis</option>
                    <option value="blocked">Blocked</option>
                </select>
                <span class="text-xs text-zinc-600 ml-auto">{{ songs.total }} songs</span>
            </div>

            <!-- Table -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-700 text-xs uppercase tracking-wider text-zinc-500">
                            <th class="text-left px-4 py-2.5 font-medium">Title</th>
                            <th class="text-left px-4 py-2.5 font-medium">Artist</th>
                            <th class="text-left px-4 py-2.5 font-medium w-16">Length</th>
                            <th class="text-left px-4 py-2.5 font-medium">Tags</th>
                            <th class="px-4 py-2.5 w-36"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <template v-for="song in songs.data" :key="song.id">
                            <!-- Song row -->
                            <tr class="hover:bg-zinc-800/40 transition-colors" :class="{ 'opacity-50': song.blocked }">
                                <td class="px-4 py-3 font-mono text-xs" :class="song.blocked ? 'text-red-400 line-through' : 'text-zinc-200'">
                                    <span
                                        class="inline-block w-1.5 h-1.5 rounded-full mr-1.5 align-middle"
                                        :class="song.has_shape_note ? 'bg-green-500/70' : 'bg-zinc-600'"
                                        :title="song.has_shape_note ? 'Has audio-feature analysis (shape note)' : 'Not yet analyzed — no shape note'"
                                    />
                                    <a :href="`/encyclopedia/songs/${song.id}`" target="_blank" class="hover:text-red-400 transition-colors">{{ song.title }}</a>
                                </td>
                                <td class="px-4 py-3 text-zinc-400 text-xs">{{ song.artist ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-500 text-xs font-mono">{{ secondsToClock(song.duration_seconds) || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="tag in song.tags"
                                            :key="tag"
                                            class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400"
                                        >{{ tagLabel(tag) }}</span>
                                        <span v-if="!song.tags?.length" class="text-xs text-zinc-700">—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            :href="`/encyclopedia/songs/${song.id}`"
                                            target="_blank"
                                            class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-500 hover:text-zinc-300 transition-colors"
                                            title="View in encyclopedia"
                                        >view</a>
                                        <button
                                            @click="toggleBlocked(song)"
                                            class="text-xs px-2 py-1 rounded transition-colors"
                                            :class="song.blocked
                                                ? 'bg-red-900/40 hover:bg-red-900/60 text-red-400 hover:text-red-300'
                                                : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-500 hover:text-red-400'"
                                            :title="song.blocked ? 'Unblock song' : 'Block song from playing'"
                                        >
                                            {{ song.blocked ? 'unblock' : 'block' }}
                                        </button>
                                        <button
                                            @click="editingId === song.id ? cancelEdit() : openEdit(song)"
                                            class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-zinc-200 transition-colors"
                                            :class="{ 'ring-1 ring-indigo-500': editingId === song.id }"
                                        >
                                            edit
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Inline details editor -->
                            <tr v-if="editingId === song.id" class="bg-zinc-800/30">
                                <td colspan="5" class="px-4 py-3">
                                    <div class="space-y-3">
                                        <div class="flex flex-wrap items-start gap-6">
                                            <div class="space-y-1">
                                                <label class="block text-xs text-zinc-500">Length (m:ss)</label>
                                                <input
                                                    v-model="detailsForm.duration"
                                                    type="text"
                                                    placeholder="3:42"
                                                    class="bg-zinc-900 border border-zinc-600 rounded px-2 py-1 text-xs text-zinc-200 font-mono w-20 focus:outline-none focus:border-zinc-400"
                                                />
                                            </div>
                                            <div class="space-y-1">
                                                <label class="block text-xs text-zinc-500">Tag</label>
                                                <select
                                                    v-model="detailsForm.tag"
                                                    class="bg-zinc-900 border border-zinc-600 rounded px-2 py-1 text-xs text-zinc-200 focus:outline-none focus:border-zinc-400"
                                                >
                                                    <option value="">none</option>
                                                    <option v-for="tag in allTags" :key="tag" :value="tag">{{ tagLabel(tag) }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-zinc-500 mb-1">Lyrics</label>
                                            <textarea
                                                v-model="detailsForm.lyrics"
                                                rows="10"
                                                class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-xs text-zinc-200 font-mono focus:outline-none focus:border-zinc-400 resize-y"
                                                placeholder="Paste lyrics here…"
                                            />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="saveDetails(song)"
                                                :disabled="detailsForm.processing"
                                                class="text-xs px-3 py-1.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white transition-colors disabled:opacity-50"
                                            >
                                                {{ detailsForm.processing ? 'Saving…' : 'Save' }}
                                            </button>
                                            <button
                                                @click="cancelEdit"
                                                class="text-xs px-3 py-1.5 rounded bg-zinc-700 hover:bg-zinc-600 text-zinc-300 transition-colors"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="songs.last_page > 1" class="flex items-center justify-between text-xs text-zinc-500">
                <span>Page {{ songs.current_page }} of {{ songs.last_page }}</span>
                <div class="flex gap-2">
                    <a
                        v-if="songs.prev_page_url"
                        :href="songs.prev_page_url"
                        class="px-3 py-1.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition-colors"
                    >← Prev</a>
                    <a
                        v-if="songs.next_page_url"
                        :href="songs.next_page_url"
                        class="px-3 py-1.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition-colors"
                    >Next →</a>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
