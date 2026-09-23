<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import axios from 'axios';

const props = defineProps({
    people:  Object,
    filters: Object,
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get('/admin/people', { search: search.value }, { preserveState: true, replace: true });
}

// ── Edit person ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm  = reactive({ name: '', born: '', died: '', primary_instrument: '', bio: '', story: '' });

function openEdit(p) {
    editingId.value              = p.id;
    editForm.name                = p.name;
    editForm.born                = p.born ?? '';
    editForm.died                = p.died ?? '';
    editForm.primary_instrument  = p.primary_instrument ?? '';
    editForm.bio                 = p.bio ?? '';
    editForm.story               = p.story ?? '';
}

function saveEdit(id) {
    router.patch(`/admin/people/${id}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

// ── Add person ────────────────────────────────────────────────────────────────
const showAddForm = ref(false);
const addForm     = reactive({ name: '', born: '', died: '', primary_instrument: '', bio: '', story: '' });

function saveAdd() {
    if (!addForm.name) return;
    router.post('/admin/people', { ...addForm }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddForm.value = false;
            Object.assign(addForm, { name: '', born: '', died: '', primary_instrument: '', bio: '', story: '' });
        },
    });
}

// ── Merge ─────────────────────────────────────────────────────────────────────
const mergingId      = ref(null);
const mergeSearch    = ref('');
const mergeResults   = ref([]);
const mergeTargetId  = ref(null);
const mergeTargetName= ref('');

function openMerge(p) {
    mergingId.value     = p.id;
    mergeSearch.value   = '';
    mergeResults.value  = [];
    mergeTargetId.value = null;
    mergeTargetName.value = '';
}

async function searchMergeTarget() {
    if (mergeSearch.value.length < 2) { mergeResults.value = []; return; }
    const { data } = await axios.get('/admin/people/search', { params: { q: mergeSearch.value } });
    mergeResults.value = data.filter(p => p.id !== mergingId.value);
}

function selectMergeTarget(p) {
    mergeTargetId.value    = p.id;
    mergeTargetName.value  = p.name;
    mergeSearch.value      = p.name;
    mergeResults.value     = [];
}

function confirmMerge(loserId) {
    if (!mergeTargetId.value) return;
    if (!confirm(`Merge this person INTO "${mergeTargetName.value}"? The current record will be deleted.`)) return;
    router.post(`/admin/people/${loserId}/merge`, { target_id: mergeTargetId.value }, {
        preserveScroll: true,
        onSuccess: () => { mergingId.value = null; },
    });
}
</script>

<template>
    <Head title="People — Admin" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-zinc-100">People</h1>
                    <p class="text-sm text-zinc-400 mt-0.5">Musicians, producers, and other credited people.</p>
                </div>
                <button
                    @click="showAddForm = !showAddForm"
                    class="px-4 py-2 bg-red-700 hover:bg-red-600 text-sm text-white font-medium rounded-lg transition-colors"
                >+ New Person</button>
            </div>
        </template>

        <!-- Add form -->
        <div v-if="showAddForm" class="mb-6 bg-zinc-900 border border-zinc-700 rounded-lg p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-300">New Person</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs text-zinc-500 mb-1">Name *</label>
                    <input v-model="addForm.name" type="text" placeholder="Full name" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Primary Instrument</label>
                    <input v-model="addForm.primary_instrument" type="text" placeholder="e.g. Vocals" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Born (year)</label>
                    <input v-model="addForm.born" type="number" placeholder="e.g. 1975" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Died (year)</label>
                    <input v-model="addForm.died" type="number" placeholder="leave blank if alive" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-zinc-500 mb-1">Bio</label>
                    <textarea v-model="addForm.bio" rows="3" placeholder="Short factual bio…" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500 resize-none" />
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-zinc-500 mb-1">Story / narrative</label>
                    <textarea v-model="addForm.story" rows="2" placeholder="Longer narrative or interesting context…" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500 resize-none" />
                </div>
            </div>
            <div class="flex gap-2">
                <button @click="saveAdd" :disabled="!addForm.name" class="px-4 py-2 bg-red-700 hover:bg-red-600 disabled:opacity-40 text-white text-sm font-medium rounded transition-colors">Save</button>
                <button @click="showAddForm = false" class="px-4 py-2 text-zinc-400 hover:text-zinc-200 text-sm transition-colors">Cancel</button>
            </div>
        </div>

        <!-- Search -->
        <form @submit.prevent="applySearch" class="flex gap-3 mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Search people…"
                class="flex-1 max-w-sm bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-100 placeholder-zinc-500 px-3 py-2 focus:outline-none focus:border-zinc-500 transition-colors"
            />
            <button type="submit" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-sm text-white rounded-lg transition-colors">Search</button>
        </form>

        <!-- People table -->
        <div class="space-y-2">
            <div
                v-for="p in people.data"
                :key="p.id"
                class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden"
            >
                <!-- View row -->
                <div v-if="editingId !== p.id && mergingId !== p.id" class="flex items-center justify-between px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-white">{{ p.name }}</span>
                            <span v-if="p.primary_instrument" class="text-xs text-zinc-500">{{ p.primary_instrument }}</span>
                            <span v-if="p.born" class="text-xs text-zinc-600">b. {{ p.born }}</span>
                            <span v-if="p.died" class="text-xs text-zinc-600">d. {{ p.died }}</span>
                            <span class="text-xs text-zinc-600">{{ p.memberships_count }} band{{ p.memberships_count !== 1 ? 's' : '' }}</span>
                        </div>
                        <p v-if="p.bio" class="text-xs text-zinc-500 mt-0.5 truncate max-w-xl">{{ p.bio }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-4">
                        <button @click="openEdit(p)" class="text-xs text-zinc-500 hover:text-zinc-200 transition-colors px-2 py-1">Edit</button>
                        <button @click="openMerge(p)" class="text-xs text-zinc-600 hover:text-amber-400 transition-colors px-2 py-1">Merge</button>
                    </div>
                </div>

                <!-- Edit form -->
                <div v-else-if="editingId === p.id" class="px-4 py-4 space-y-4">
                    <p class="text-xs font-medium text-zinc-400">Editing {{ p.name }}</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-xs text-zinc-500 mb-1">Name</label>
                            <input v-model="editForm.name" type="text" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-zinc-500 mb-1">Primary Instrument</label>
                            <input v-model="editForm.primary_instrument" type="text" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-zinc-500 mb-1">Born (year)</label>
                            <input v-model="editForm.born" type="number" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-zinc-500 mb-1">Died (year)</label>
                            <input v-model="editForm.died" type="number" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-zinc-500 mb-1">Bio</label>
                            <textarea v-model="editForm.bio" rows="3" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500 resize-none" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-zinc-500 mb-1">Story / narrative</label>
                            <textarea v-model="editForm.story" rows="2" class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500 resize-none" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="saveEdit(p.id)" class="px-3 py-1.5 bg-red-700 hover:bg-red-600 text-white text-xs font-medium rounded transition-colors">Save</button>
                        <button @click="editingId = null" class="px-3 py-1.5 text-zinc-400 hover:text-zinc-200 text-xs transition-colors">Cancel</button>
                    </div>
                </div>

                <!-- Merge form -->
                <div v-else-if="mergingId === p.id" class="px-4 py-4 bg-amber-950/20 border-t border-amber-900/30 space-y-3">
                    <p class="text-xs font-medium text-amber-400">Merging "{{ p.name }}" — this record will be deleted after merge</p>
                    <div class="relative max-w-xs">
                        <label class="block text-xs text-zinc-500 mb-1">Merge INTO (keep this person)</label>
                        <input
                            v-model="mergeSearch"
                            @input="searchMergeTarget"
                            type="text"
                            placeholder="Search for the record to keep…"
                            class="w-full bg-zinc-900 border border-zinc-700 rounded text-sm text-zinc-100 placeholder-zinc-500 px-2.5 py-1.5 focus:outline-none focus:border-zinc-500"
                        />
                        <div v-if="mergeResults.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                            <button
                                v-for="r in mergeResults"
                                :key="r.id"
                                @click="selectMergeTarget(r)"
                                class="w-full text-left px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700"
                            >{{ r.name }}</button>
                        </div>
                        <p v-if="mergeTargetId" class="text-xs text-green-500 mt-1">✓ Will merge into: {{ mergeTargetName }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="confirmMerge(p.id)" :disabled="!mergeTargetId" class="px-3 py-1.5 bg-amber-700 hover:bg-amber-600 disabled:opacity-40 text-white text-xs font-medium rounded transition-colors">Merge & Delete</button>
                        <button @click="mergingId = null" class="px-3 py-1.5 text-zinc-400 hover:text-zinc-200 text-xs transition-colors">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="people.last_page > 1" class="flex justify-center gap-2 mt-6">
            <a
                v-for="link in people.links"
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
