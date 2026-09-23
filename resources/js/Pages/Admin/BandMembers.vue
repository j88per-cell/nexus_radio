<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    artists:     Object,
    filters:     Object,
    instruments: Array,
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get('/admin/band-members', { search: search.value }, { preserveState: true, replace: true });
}

// ── Per-band member list (loaded on demand) ───────────────────────────────────
const expandedArtist = ref(null);
const membersCache   = reactive({});
const loadingFor     = ref(null);

async function toggleArtist(artist) {
    if (expandedArtist.value === artist.id) {
        expandedArtist.value = null;
        return;
    }
    expandedArtist.value = artist.id;
    if (!membersCache[artist.id]) {
        loadingFor.value = artist.id;
        const { data } = await axios.get(`/admin/band-members/${artist.id}/members`);
        membersCache[artist.id] = data;
        loadingFor.value = null;
    }
}

// ── Add member form ───────────────────────────────────────────────────────────
const addingFor    = ref(null);
const addForm      = reactive({ artist_id: null, person_id: null, start_year: '', end_year: '', departure_reason: '', notes: '', instrument_ids: [] });
const personSearch  = ref('');
const personResults = ref([]);
const personLabel   = ref('');

async function searchPeople() {
    if (personSearch.value.length < 2) { personResults.value = []; return; }
    const { data } = await axios.get('/admin/band-members/search-people', { params: { q: personSearch.value } });
    personResults.value = data;
}

function selectPerson(p) {
    addForm.person_id   = p.id;
    personLabel.value   = p.name;
    personSearch.value  = p.name;
    personResults.value = [];
}

function openAddForm(artist) {
    addingFor.value        = artist.id;
    addForm.artist_id      = artist.id;
    addForm.person_id      = null;
    addForm.start_year     = '';
    addForm.end_year       = '';
    addForm.departure_reason = '';
    addForm.notes          = '';
    addForm.instrument_ids = [];
    personSearch.value     = '';
    personLabel.value      = '';
    personResults.value    = [];
}

function saveAdd(artistId) {
    if (!addForm.person_id) return;
    router.post(`/admin/band-members/${artistId}`, { ...addForm }, {
        preserveScroll: true,
        onSuccess: () => {
            addingFor.value = null;
            delete membersCache[artistId];
            toggleArtist({ id: artistId });
        },
    });
}

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId   = ref(null);
const editForm    = reactive({ start_year: '', end_year: '', departure_reason: '', notes: '', instrument_ids: [] });

function openEdit(m) {
    editingId.value          = m.membership_id;
    editForm.start_year      = m.start_year ?? '';
    editForm.end_year        = m.end_year ?? '';
    editForm.departure_reason= m.departure_reason ?? '';
    editForm.notes           = m.notes ?? '';
    editForm.instrument_ids  = [...(m.instrument_ids ?? [])];
}

function saveEdit(artistId) {
    router.patch(`/admin/band-members/membership/${editingId.value}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            delete membersCache[artistId];
            toggleArtist({ id: artistId });
        },
    });
}

function removeMember(membershipId, artistId) {
    if (!confirm('Remove this member?')) return;
    router.delete(`/admin/band-members/membership/${membershipId}`, {
        preserveScroll: true,
        onSuccess: () => {
            delete membersCache[artistId];
            toggleArtist({ id: artistId });
        },
    });
}

function toggleInstrument(id, form) {
    const idx = form.instrument_ids.indexOf(id);
    if (idx === -1) form.instrument_ids.push(id);
    else form.instrument_ids.splice(idx, 1);
}

const departureReasons = [
    { value: '',              label: '— still active —' },
    { value: 'quit',          label: 'Quit' },
    { value: 'fired',         label: 'Fired' },
    { value: 'hiatus',        label: 'Hiatus' },
    { value: 'death',         label: 'Died' },
    { value: 'project_ended', label: 'Project ended' },
];

const instrumentsByCategory = computed(() => {
    const groups = {};
    for (const i of props.instruments) {
        const cat = i.category ?? 'other';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(i);
    }
    return groups;
});
</script>

<template>
    <Head title="Band Members — Admin" />
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-zinc-100">Band Members</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Manage band rosters — current members, alumni, instruments, and years.</p>
        </template>

        <!-- Search -->
        <form @submit.prevent="applySearch" class="flex gap-3 mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Search bands…"
                class="flex-1 max-w-sm bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-100 placeholder-zinc-500 px-3 py-2 focus:outline-none focus:border-zinc-500 transition-colors"
            />
            <button type="submit" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-sm text-white rounded-lg transition-colors">Search</button>
        </form>

        <!-- Artist list -->
        <div class="space-y-2">
            <div
                v-for="artist in artists.data"
                :key="artist.id"
                class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden"
            >
                <!-- Artist row -->
                <div class="flex items-center justify-between px-4 py-3">
                    <button
                        @click="toggleArtist(artist)"
                        class="flex items-center gap-3 text-left group"
                    >
                        <svg
                            class="w-4 h-4 text-zinc-500 transition-transform shrink-0"
                            :class="expandedArtist === artist.id ? 'rotate-90' : ''"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="font-semibold text-white group-hover:text-red-400 transition-colors">{{ artist.name }}</span>
                        <span class="text-xs text-zinc-600">{{ artist.members_count }} member{{ artist.members_count !== 1 ? 's' : '' }}</span>
                    </button>
                    <button
                        @click="openAddForm(artist); expandedArtist = artist.id"
                        class="text-xs px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 hover:border-zinc-600 text-zinc-300 rounded-lg transition-colors"
                    >+ Add Member</button>
                </div>

                <!-- Expanded member list -->
                <div v-if="expandedArtist === artist.id" class="border-t border-zinc-800">

                    <!-- Loading -->
                    <div v-if="loadingFor === artist.id" class="px-4 py-3 text-xs text-zinc-500">Loading…</div>

                    <!-- Members -->
                    <div v-else-if="membersCache[artist.id]" class="divide-y divide-zinc-800">

                        <div v-if="!membersCache[artist.id].length" class="px-4 py-3 text-xs text-zinc-600">No members yet.</div>

                        <div v-for="m in membersCache[artist.id]" :key="m.membership_id">

                            <!-- View row -->
                            <div v-if="editingId !== m.membership_id" class="flex items-center justify-between px-4 py-2.5">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span
                                        class="text-xs px-2 py-0.5 rounded font-medium shrink-0"
                                        :class="m.is_current ? 'bg-green-900/50 text-green-400' : 'bg-zinc-800 text-zinc-500'"
                                    >{{ m.is_current ? 'Current' : 'Former' }}</span>
                                    <Link :href="`/encyclopedia/people/${m.person_slug}`" class="font-medium text-zinc-200 hover:text-red-400 transition-colors">{{ m.person_name }}</Link>
                                    <span v-if="m.instruments.length" class="text-xs text-zinc-500">{{ m.instruments.join(', ') }}</span>
                                    <span class="text-xs text-zinc-600">
                                        {{ m.start_year ?? '?' }}{{ m.end_year ? `–${m.end_year}` : (m.is_current ? '–present' : '') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button @click="openEdit(m)" class="text-xs text-zinc-500 hover:text-zinc-200 transition-colors px-2 py-1">Edit</button>
                                    <button @click="removeMember(m.membership_id, artist.id)" class="text-zinc-600 hover:text-red-400 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Edit row -->
                            <div v-else class="px-4 py-3 bg-zinc-800/50 space-y-3">
                                <p class="text-xs font-medium text-zinc-400">Editing {{ m.person_name }}</p>
                                <div class="flex flex-wrap gap-3">
                                    <div>
                                        <label class="block text-xs text-zinc-500 mb-1">Start year</label>
                                        <input v-model="editForm.start_year" type="number" placeholder="e.g. 1991" class="w-28 bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-zinc-500 mb-1">End year</label>
                                        <input v-model="editForm.end_year" type="number" placeholder="blank = current" class="w-36 bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-zinc-500 mb-1">Departure</label>
                                        <select v-model="editForm.departure_reason" class="bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-200 px-2.5 py-1.5 focus:outline-none">
                                            <option v-for="r in departureReasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- Instruments -->
                                <div>
                                    <label class="block text-xs text-zinc-500 mb-2">Instruments</label>
                                    <div class="flex flex-wrap gap-2">
                                        <template v-for="(instruments, cat) in instrumentsByCategory" :key="cat">
                                            <button
                                                v-for="inst in instruments"
                                                :key="inst.id"
                                                type="button"
                                                @click="toggleInstrument(inst.id, editForm)"
                                                class="text-xs px-2.5 py-1 rounded border transition-colors"
                                                :class="editForm.instrument_ids.includes(inst.id)
                                                    ? 'bg-red-700 border-red-600 text-white'
                                                    : 'bg-zinc-900 border-zinc-700 text-zinc-400 hover:border-zinc-500'"
                                            >{{ inst.name }}</button>
                                        </template>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-zinc-500 mb-1">Notes</label>
                                    <input v-model="editForm.notes" type="text" placeholder="Optional notes" class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                                </div>
                                <div class="flex gap-2">
                                    <button @click="saveEdit(artist.id)" class="px-3 py-1.5 bg-red-700 hover:bg-red-600 text-white text-xs font-medium rounded transition-colors">Save</button>
                                    <button @click="editingId = null" class="px-3 py-1.5 text-zinc-400 hover:text-zinc-200 text-xs transition-colors">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add member form -->
                    <div v-if="addingFor === artist.id" class="px-4 py-3 bg-zinc-800/30 border-t border-zinc-800 space-y-3">
                        <p class="text-xs font-medium text-zinc-400">Add member to {{ artist.name }}</p>

                        <!-- Person typeahead -->
                        <div class="relative max-w-xs">
                            <label class="block text-xs text-zinc-500 mb-1">Person</label>
                            <input
                                v-model="personSearch"
                                @input="searchPeople"
                                type="text"
                                placeholder="Search people…"
                                class="w-full bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 placeholder-zinc-500 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500"
                            />
                            <div v-if="personResults.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                                <button
                                    v-for="p in personResults"
                                    :key="p.id"
                                    @click="selectPerson(p)"
                                    class="w-full text-left px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700"
                                >{{ p.name }}</button>
                            </div>
                            <p v-if="addForm.person_id" class="text-xs text-green-500 mt-1">✓ {{ personLabel }}</p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <div>
                                <label class="block text-xs text-zinc-500 mb-1">Start year</label>
                                <input v-model="addForm.start_year" type="number" placeholder="e.g. 1991" class="w-28 bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-zinc-500 mb-1">End year</label>
                                <input v-model="addForm.end_year" type="number" placeholder="blank = current" class="w-36 bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-zinc-500 mb-1">Departure</label>
                                <select v-model="addForm.departure_reason" class="bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-200 px-2.5 py-1.5 focus:outline-none">
                                    <option v-for="r in departureReasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Instruments -->
                        <div>
                            <label class="block text-xs text-zinc-500 mb-2">Instruments</label>
                            <div class="flex flex-wrap gap-2">
                                <template v-for="(instruments, cat) in instrumentsByCategory" :key="cat">
                                    <button
                                        v-for="inst in instruments"
                                        :key="inst.id"
                                        type="button"
                                        @click="toggleInstrument(inst.id, addForm)"
                                        class="text-xs px-2.5 py-1 rounded border transition-colors"
                                        :class="addForm.instrument_ids.includes(inst.id)
                                            ? 'bg-red-700 border-red-600 text-white'
                                            : 'bg-zinc-900 border-zinc-700 text-zinc-400 hover:border-zinc-500'"
                                    >{{ inst.name }}</button>
                                </template>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button @click="saveAdd(artist.id)" :disabled="!addForm.person_id" class="px-3 py-1.5 bg-red-700 hover:bg-red-600 disabled:opacity-40 text-white text-xs font-medium rounded transition-colors">Save</button>
                            <button @click="addingFor = null" class="px-3 py-1.5 text-zinc-400 hover:text-zinc-200 text-xs transition-colors">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="artists.last_page > 1" class="flex justify-center gap-2 mt-6">
            <a
                v-for="link in artists.links"
                :key="link.label"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="px-3 py-1.5 text-sm rounded border transition-colors"
                :class="link.active
                    ? 'bg-zinc-700 border-zinc-600 text-white'
                    : link.url
                        ? 'border-zinc-700 text-zinc-400 hover:border-zinc-500 hover:text-white'
                        : 'border-zinc-800 text-zinc-600 cursor-not-allowed'"
            />
        </div>

    </AdminLayout>
</template>
