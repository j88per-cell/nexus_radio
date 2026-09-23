<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    artists: Object,
    filters: Object,
    allGenres: Array,
});

const f = reactive({
    search: props.filters.search ?? '',
    filter: props.filters.filter ?? '',
});

function applyFilters() {
    router.get('/admin/artists', f, { preserveState: true, replace: true });
}

function clearFilters() {
    Object.assign(f, { search: '', filter: '' });
    applyFilters();
}

// Inline editing
const editing = ref(null);
const editForm = reactive({
    name:             '',
    origin:           '',
    formed_year:      '',
    disbanded_year:   '',
    bio:              '',
    story:            '',
    genre_ids:        [],
    primary_genre_id: null,
});

function startEdit(artist) {
    editing.value              = artist.id;
    editForm.name              = artist.name           ?? '';
    editForm.origin            = artist.origin         ?? '';
    editForm.formed_year       = artist.formed_year    ?? '';
    editForm.disbanded_year    = artist.disbanded_year ?? '';
    editForm.bio               = artist.bio            ?? '';
    editForm.story             = artist.story          ?? '';
    editForm.genre_ids         = (artist.genres ?? []).map(g => g.id);
    editForm.primary_genre_id  = (artist.genres ?? []).find(g => g.primary)?.id ?? null;
    mergeSearch.value          = '';
    mergeTarget.value          = null;
    mergeCandidates.value      = [];
}

function cancelEdit() {
    editing.value = null;
}

function saveEdit(artist) {
    router.patch(`/admin/artists/${artist.id}`, {
        name:             editForm.name           || null,
        origin:           editForm.origin         || null,
        formed_year:      editForm.formed_year     || null,
        disbanded_year:   editForm.disbanded_year  || null,
        bio:              editForm.bio             || null,
        story:            editForm.story           || null,
        genre_ids:        editForm.genre_ids,
        primary_genre_id: editForm.primary_genre_id,
    }, {
        preserveState: true,
        onSuccess: () => { editing.value = null; },
    });
}

function toggleGenre(genreId) {
    const idx = editForm.genre_ids.indexOf(genreId);
    if (idx === -1) {
        editForm.genre_ids.push(genreId);
        if (editForm.primary_genre_id === null) {
            editForm.primary_genre_id = genreId;
        }
    } else {
        editForm.genre_ids.splice(idx, 1);
        if (editForm.primary_genre_id === genreId) {
            editForm.primary_genre_id = editForm.genre_ids[0] ?? null;
        }
    }
}

// Influences
const influenceSearch   = ref('');
const influenceResults  = ref([]);

async function searchInfluences() {
    if (influenceSearch.value.length < 2) { influenceResults.value = []; return; }
    const { data } = await axios.get('/admin/artists/search', { params: { q: influenceSearch.value } });
    influenceResults.value = data;
}

function addInfluence(artistId, influenceId) {
    router.post(`/admin/artists/${artistId}/influences`, { influence_id: influenceId }, {
        preserveScroll: true,
        onSuccess: () => { influenceSearch.value = ''; influenceResults.value = []; },
    });
}

function removeInfluence(artistId, influenceId) {
    router.delete(`/admin/artists/${artistId}/influences/${influenceId}`, {}, { preserveScroll: true });
}

// Merge
const mergeSearch      = ref('');
const mergeTarget      = ref(null);
const mergeCandidates  = ref([]);

async function searchMergeTarget() {
    if (mergeSearch.value.length < 2) { mergeCandidates.value = []; return; }
    const res = await fetch(`/admin/artists/search?q=${encodeURIComponent(mergeSearch.value)}`);
    mergeCandidates.value = await res.json();
}

function selectMergeTarget(candidate) {
    mergeTarget.value     = candidate;
    mergeSearch.value     = candidate.name;
    mergeCandidates.value = [];
}

function confirmMerge(artist) {
    if (! mergeTarget.value) return;
    if (! confirm(`Merge "${artist.name}" (#${artist.id}) into "${mergeTarget.value.name}" (#${mergeTarget.value.id})? This cannot be undone.`)) return;
    router.post(`/admin/artists/${artist.id}/merge`, { target_id: mergeTarget.value.id }, {
        onSuccess: () => { editing.value = null; },
    });
}

function confirmDelete(artist) {
    if (! confirm(`Remove "${artist.name}" and all its releases/songs from the library? This cannot be undone.`)) return;
    router.delete(`/admin/artists/${artist.id}`, {}, {
        onSuccess: () => { editing.value = null; },
    });
}

// Bulk selection
const selected = ref([]);
const allChecked = computed(() => props.artists.data.length > 0 && selected.value.length === props.artists.data.length);

function toggleAll() {
    selected.value = allChecked.value ? [] : props.artists.data.map(a => a.id);
}

function confirmBulkDelete() {
    if (! selected.value.length) return;
    if (! confirm(`Remove ${selected.value.length} artists and all their releases/songs from the library? This cannot be undone.`)) return;
    router.post('/admin/artists/bulk-destroy', { ids: selected.value }, {
        onSuccess: () => { selected.value = []; },
    });
}

const coverageFilters = [
    { value: '',          label: 'All artists' },
    { value: 'no_bio',    label: 'Missing bio' },
    { value: 'no_origin', label: 'Missing origin' },
];
</script>

<template>
    <Head title="Artists" />
    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-zinc-100">Artists</h1>
        </template>

        <!-- Filters -->
        <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-4 mb-5 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <input
                    v-model="f.search"
                    @keydown.enter="applyFilters"
                    placeholder="Search name, origin…"
                    class="w-full bg-zinc-800 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                />
            </div>

            <select
                v-model="f.filter"
                @change="applyFilters"
                class="bg-zinc-800 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 focus:outline-none focus:border-zinc-400"
            >
                <option v-for="opt in coverageFilters" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <button @click="applyFilters" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-base text-zinc-100 rounded transition-colors">
                Search
            </button>
            <button @click="clearFilters" class="px-4 py-2 text-base text-zinc-300 hover:text-zinc-100 transition-colors">
                Clear
            </button>

            <span class="ml-auto text-sm text-zinc-400">{{ artists.total.toLocaleString() }} artists</span>
        </div>

        <!-- Bulk action bar -->
        <div v-if="selected.length" class="bg-zinc-800 border border-zinc-700 rounded-lg px-4 py-3 mb-3 flex items-center gap-4">
            <span class="text-sm text-zinc-300">{{ selected.length }} selected</span>
            <button @click="confirmBulkDelete" class="px-4 py-1.5 bg-red-900 hover:bg-red-800 text-sm text-red-200 rounded transition-colors">
                Remove from library
            </button>
            <button @click="selected = []" class="text-sm text-zinc-500 hover:text-zinc-300 transition-colors">Clear</button>
        </div>

        <!-- Table -->
        <div class="bg-zinc-900 border border-zinc-700 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-base">
                    <thead>
                        <tr class="border-b border-zinc-700 text-sm uppercase tracking-wider text-zinc-400">
                            <th class="px-4 py-3 w-8">
                                <input type="checkbox" :checked="allChecked" @change="toggleAll" class="accent-zinc-400" />
                            </th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left hidden lg:table-cell">Genre</th>
                            <th class="px-4 py-3 text-left hidden lg:table-cell">Origin</th>
                            <th class="px-4 py-3 text-left hidden xl:table-cell">Formed</th>
                            <th class="px-4 py-3 text-center hidden lg:table-cell" title="Has bio">Bio</th>
                            <th class="px-4 py-3 w-20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="artist in artists.data" :key="artist.id">
                            <!-- Normal row -->
                            <tr
                                v-if="editing !== artist.id"
                                class="border-b border-zinc-800 hover:bg-zinc-800/40 transition-colors"
                            >
                                <td class="px-4 py-3 w-8">
                                    <input type="checkbox" :value="artist.id" v-model="selected" class="accent-zinc-400" />
                                </td>
                                <td class="px-4 py-3 text-zinc-100 font-medium">{{ artist.name }}</td>
                                <td class="px-4 py-3 text-zinc-300 hidden lg:table-cell">{{ artist.genre ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-300 hidden lg:table-cell">{{ artist.origin ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-300 hidden xl:table-cell">
                                    {{ artist.formed_year ?? '—' }}
                                    <span v-if="artist.disbanded_year" class="text-zinc-400">–{{ artist.disbanded_year }}</span>
                                </td>
                                <td class="px-4 py-3 text-center hidden lg:table-cell">
                                    <span class="w-2.5 h-2.5 rounded-full inline-block" :class="artist.has_bio ? 'bg-green-500' : 'bg-zinc-600'" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="startEdit(artist)"
                                        class="text-sm text-zinc-300 hover:text-white transition-colors px-2 py-1 rounded hover:bg-zinc-700"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>

                            <!-- Expanded edit row -->
                            <tr v-else class="bg-zinc-800/50 border-b border-zinc-700">
                                <td colspan="7" class="px-4 py-5">
                                    <div class="space-y-4">
                                        <p class="text-base font-semibold text-zinc-100">{{ artist.name }} <span class="text-sm text-zinc-400 font-normal">#{{ artist.id }}</span></p>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-1">Name</label>
                                                <input
                                                    v-model="editForm.name"
                                                    class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-1">Origin</label>
                                                <input
                                                    v-model="editForm.origin"
                                                    placeholder="e.g. Oslo, Norway"
                                                    class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-1">Formed</label>
                                                <input
                                                    v-model="editForm.formed_year"
                                                    type="number"
                                                    placeholder="e.g. 1991"
                                                    class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-1">Disbanded</label>
                                                <input
                                                    v-model="editForm.disbanded_year"
                                                    type="number"
                                                    placeholder="leave blank if active"
                                                    class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                                                />
                                            </div>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-2">Genres</label>
                                            <div class="flex flex-wrap gap-1.5">
                                                <button
                                                    v-for="genre in allGenres"
                                                    :key="genre.id"
                                                    type="button"
                                                    @click="toggleGenre(genre.id)"
                                                    class="px-2.5 py-1 rounded-full text-sm transition-colors border"
                                                    :class="editForm.genre_ids.includes(genre.id)
                                                        ? 'bg-zinc-700 border-zinc-500 text-zinc-100'
                                                        : 'bg-transparent border-zinc-600 text-zinc-300 hover:border-zinc-400 hover:text-zinc-100'"
                                                >
                                                    {{ genre.name }}
                                                    <span
                                                        v-if="editForm.genre_ids.includes(genre.id)"
                                                        @click.stop="editForm.primary_genre_id = genre.id"
                                                        :title="editForm.primary_genre_id === genre.id ? 'Primary' : 'Set as primary'"
                                                        class="ml-1 transition-colors"
                                                        :class="editForm.primary_genre_id === genre.id ? 'text-amber-400' : 'text-zinc-400 hover:text-amber-400'"
                                                    >★</span>
                                                </button>
                                            </div>
                                            <p v-if="editForm.primary_genre_id" class="text-sm text-zinc-400 mt-1.5">
                                                ★ = primary genre shown in table
                                            </p>
                                        </div>

                                        <div>
                                            <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-1">Bio</label>
                                            <textarea
                                                v-model="editForm.bio"
                                                rows="4"
                                                placeholder="Artist biography…"
                                                class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400 resize-y"
                                            />
                                        </div>

                                        <div>
                                            <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-1">
                                                Story
                                                <span class="normal-case text-zinc-400">(used by Penny for DJ drops)</span>
                                            </label>
                                            <textarea
                                                v-model="editForm.story"
                                                rows="3"
                                                placeholder="Short narrative Penny can use when introducing this artist…"
                                                class="w-full bg-zinc-900 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400 resize-y"
                                            />
                                        </div>

                                        <!-- Influences -->
                                        <div>
                                            <label class="text-sm text-zinc-400 uppercase tracking-wider block mb-2">Influenced By</label>
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                <span
                                                    v-for="inf in artist.influences"
                                                    :key="inf.id"
                                                    class="flex items-center gap-1.5 px-2.5 py-1 bg-zinc-800 border border-zinc-700 rounded-full text-sm text-zinc-300"
                                                >
                                                    {{ inf.name }}
                                                    <button @click="removeInfluence(artist.id, inf.id)" class="text-zinc-600 hover:text-red-400 transition-colors leading-none">×</button>
                                                </span>
                                                <span v-if="!artist.influences.length" class="text-sm text-zinc-600">None added yet.</span>
                                            </div>
                                            <div class="relative max-w-xs">
                                                <input
                                                    v-model="influenceSearch"
                                                    @input="searchInfluences"
                                                    type="text"
                                                    placeholder="Add influence…"
                                                    class="w-full bg-zinc-900 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:border-zinc-500"
                                                />
                                                <div v-if="influenceResults.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                                                    <button
                                                        v-for="r in influenceResults"
                                                        :key="r.id"
                                                        @click="addInfluence(artist.id, r.id); influenceSearch = ''; influenceResults = []"
                                                        class="w-full text-left px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700"
                                                    >{{ r.name }}</button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex gap-2">
                                            <button @click="saveEdit(artist)" class="px-4 py-2 bg-zinc-600 hover:bg-zinc-500 text-base text-zinc-100 rounded transition-colors">
                                                Save
                                            </button>
                                            <button @click="cancelEdit" class="px-4 py-2 text-base text-zinc-300 hover:text-zinc-100 transition-colors">
                                                Cancel
                                            </button>
                                        </div>

                                        <!-- Danger zone -->
                                        <div class="border-t border-zinc-700 pt-3 mt-1 space-y-3">
                                            <p class="text-sm text-zinc-400 uppercase tracking-wider">Danger Zone</p>

                                            <!-- Merge -->
                                            <div class="flex flex-wrap gap-2 items-start">
                                                <div class="relative flex-1 min-w-48">
                                                    <input
                                                        v-model="mergeSearch"
                                                        @input="searchMergeTarget"
                                                        placeholder="Merge into…"
                                                        class="w-full bg-zinc-900 border border-zinc-700 rounded px-3 py-2 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:border-zinc-500"
                                                    />
                                                    <ul v-if="mergeCandidates.length" class="absolute z-10 w-full bg-zinc-800 border border-zinc-700 rounded mt-1 text-sm text-zinc-200 shadow-lg">
                                                        <li
                                                            v-for="c in mergeCandidates"
                                                            :key="c.id"
                                                            @click="selectMergeTarget(c)"
                                                            class="px-3 py-2 hover:bg-zinc-700 cursor-pointer flex justify-between"
                                                        >
                                                            <span>{{ c.name }}</span>
                                                            <span class="text-zinc-400 text-sm ml-3">#{{ c.id }}</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <button
                                                    @click="confirmMerge(artist)"
                                                    :disabled="!mergeTarget"
                                                    class="px-4 py-2 text-sm rounded transition-colors"
                                                    :class="mergeTarget ? 'bg-amber-700 hover:bg-amber-600 text-white' : 'bg-zinc-800 text-zinc-600 cursor-not-allowed'"
                                                >Merge</button>
                                            </div>

                                            <!-- Delete -->
                                            <div>
                                                <button
                                                    @click="confirmDelete(artist)"
                                                    class="px-4 py-1.5 bg-red-900 hover:bg-red-800 text-sm text-red-200 rounded transition-colors"
                                                >Remove from library</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="artists.last_page > 1" class="border-t border-zinc-700 px-4 py-3 flex items-center justify-between text-base text-zinc-300">
                <span>Page {{ artists.current_page }} of {{ artists.last_page }}</span>
                <div class="flex gap-2">
                    <a
                        v-if="artists.prev_page_url"
                        :href="artists.prev_page_url"
                        class="px-3 py-1 bg-zinc-800 hover:bg-zinc-700 rounded transition-colors text-zinc-300"
                    >← Prev</a>
                    <a
                        v-if="artists.next_page_url"
                        :href="artists.next_page_url"
                        class="px-3 py-1 bg-zinc-800 hover:bg-zinc-700 rounded transition-colors text-zinc-300"
                    >Next →</a>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
