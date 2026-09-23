<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import axios from 'axios';

const props = defineProps({
    releases: Object,
    filters:  Object,
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get('/admin/release-credits', { search: search.value }, { preserveState: true, replace: true });
}

// ── Add credit form per release ──────────────────────────────────────────────

// Track which release's add-form is open
const addingFor = ref(null);
const addForm   = reactive({ release_id: null, person_id: null, role: 'producer' });

const personSearch  = ref('');
const personResults = ref([]);
const personLabel   = ref('');

async function searchPeople() {
    if (personSearch.value.length < 2) { personResults.value = []; return; }
    const { data } = await axios.get('/admin/release-credits/search-people', { params: { q: personSearch.value } });
    personResults.value = data;
}

function selectPerson(p) {
    addForm.person_id  = p.id;
    personLabel.value  = p.name;
    personSearch.value = p.name;
    personResults.value = [];
}

function openAddForm(release) {
    addingFor.value   = release.id;
    addForm.release_id = release.id;
    addForm.person_id  = null;
    addForm.role       = 'producer';
    personSearch.value = '';
    personLabel.value  = '';
    personResults.value = [];
}

function saveCredit() {
    if (!addForm.person_id || !addForm.role) return;
    router.post('/admin/release-credits', { ...addForm }, {
        preserveScroll: true,
        onSuccess: () => { addingFor.value = null; },
    });
}

function removeCredit(id) {
    router.delete(`/admin/release-credits/${id}`, { preserveScroll: true });
}

const commonRoles = ['producer', 'co_producer', 'engineer', 'mixer', 'mastering', 'artwork', 'photography', 'additional_guitars', 'guest_vocalist'];
</script>

<template>
    <Head title="Release Credits — Admin" />
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-zinc-100">Release Credits</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Producers, engineers, and other credited people per release.</p>
        </template>

        <!-- Search -->
        <form @submit.prevent="applySearch" class="flex gap-3 mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Search releases or artists…"
                class="flex-1 max-w-sm bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-100 placeholder-zinc-500 px-3 py-2 focus:outline-none focus:border-zinc-500 transition-colors"
            />
            <button type="submit" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-sm text-white rounded-lg transition-colors">Search</button>
        </form>

        <!-- Releases list -->
        <div class="space-y-4">
            <div
                v-for="r in releases.data"
                :key="r.id"
                class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden"
            >
                <!-- Release header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
                    <div>
                        <Link
                            :href="`/encyclopedia/artists/${r.artist_slug}`"
                            class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors"
                        >{{ r.artist_name }}</Link>
                        <h3 class="font-semibold text-white">{{ r.title }}</h3>
                        <span class="text-xs text-zinc-500">{{ r.year }} · {{ r.type }}</span>
                    </div>
                    <button
                        @click="openAddForm(r)"
                        class="text-xs px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 hover:border-zinc-600 text-zinc-300 rounded-lg transition-colors"
                    >
                        + Add Credit
                    </button>
                </div>

                <!-- Existing credits -->
                <div v-if="r.credits.length" class="px-4 py-2 space-y-1.5">
                    <div
                        v-for="c in r.credits"
                        :key="c.id"
                        class="flex items-center justify-between text-sm"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs px-2 py-0.5 bg-zinc-800 rounded text-zinc-400 capitalize">{{ c.role.replace('_', ' ') }}</span>
                            <span class="text-zinc-200">{{ c.person_name }}</span>
                        </div>
                        <button @click="removeCredit(c.id)" class="text-zinc-600 hover:text-red-400 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div v-else class="px-4 py-2 text-xs text-zinc-600">No credits yet.</div>

                <!-- Inline add form -->
                <div v-if="addingFor === r.id" class="px-4 py-3 bg-zinc-800/50 border-t border-zinc-800">
                    <div class="flex flex-wrap gap-3 items-end">
                        <!-- Person search -->
                        <div class="relative flex-1 min-w-48">
                            <label class="block text-xs text-zinc-500 mb-1">Person</label>
                            <input
                                v-model="personSearch"
                                @input="searchPeople"
                                type="text"
                                placeholder="Search people…"
                                class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 placeholder-zinc-500 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500"
                            />
                            <div v-if="personResults.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                                <button
                                    v-for="p in personResults"
                                    :key="p.id"
                                    @click="selectPerson(p)"
                                    class="w-full text-left px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700"
                                >{{ p.name }}</button>
                            </div>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-xs text-zinc-500 mb-1">Role</label>
                            <select v-model="addForm.role" class="bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-200 px-2.5 py-1.5 focus:outline-none">
                                <option v-for="role in commonRoles" :key="role" :value="role">{{ role.replace('_', ' ') }}</option>
                                <option value="other">other…</option>
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="saveCredit"
                                :disabled="!addForm.person_id"
                                class="px-3 py-1.5 bg-red-700 hover:bg-red-600 disabled:opacity-40 text-white text-xs font-medium rounded transition-colors"
                            >Save</button>
                            <button @click="addingFor = null" class="px-3 py-1.5 text-zinc-400 hover:text-zinc-200 text-xs transition-colors">Cancel</button>
                        </div>
                    </div>
                    <p v-if="addForm.person_id" class="text-xs text-green-500 mt-1.5">✓ {{ personLabel }}</p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="releases.last_page > 1" class="flex justify-center gap-2 mt-6">
            <a
                v-for="link in releases.links"
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
