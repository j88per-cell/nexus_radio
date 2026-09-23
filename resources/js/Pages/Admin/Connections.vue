<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    connections: Object,
    types:       Array,
});

// ── Add form ────────────────────────────────────────────────────────────────

const form = reactive({
    from_type:   'artist',
    from_id:     null,
    to_type:     'artist',
    to_id:       null,
    type:        'toured_with',
    description: '',
    year:        '',
});

// Typeahead: subject
const subjectSearch  = ref('');
const subjectResults = ref([]);
const subjectLabel   = ref('');

async function searchSubject() {
    if (subjectSearch.value.length < 2) { subjectResults.value = []; return; }
    const { data } = await axios.get('/admin/connections/search', { params: { q: subjectSearch.value } });
    subjectResults.value = data.filter(r => r.type === form.from_type);
}

function selectSubject(item) {
    form.from_id    = item.id;
    subjectLabel.value  = item.name;
    subjectSearch.value = item.name;
    subjectResults.value = [];
}

// Typeahead: object
const objectSearch  = ref('');
const objectResults = ref([]);
const objectLabel   = ref('');

async function searchObject() {
    if (objectSearch.value.length < 2) { objectResults.value = []; return; }

    if (form.to_type === 'release') {
        const { data } = await axios.get('/admin/connections/search-releases', { params: { q: objectSearch.value } });
        objectResults.value = data;
    } else {
        const { data } = await axios.get('/admin/connections/search', { params: { q: objectSearch.value } });
        objectResults.value = data.filter(r => r.type === form.to_type);
    }
}

function selectObject(item) {
    form.to_id      = item.id;
    objectLabel.value   = item.name;
    objectSearch.value  = item.name;
    objectResults.value = [];
}

function clearSubject() {
    form.from_id = null;
    subjectLabel.value = '';
    subjectSearch.value = '';
}

function clearObject() {
    form.to_id = null;
    objectLabel.value = '';
    objectSearch.value = '';
}

const canSave = computed(() => form.from_id && form.to_id && form.type);

function save() {
    if (!canSave.value) return;
    router.post('/admin/connections', {
        from_type:   form.from_type,
        from_id:     form.from_id,
        to_type:     form.to_type,
        to_id:       form.to_id,
        type:        form.type,
        description: form.description || null,
        year:        form.year || null,
    }, {
        preserveState: false,
        onSuccess: () => {
            form.from_id = null; form.to_id = null;
            form.description = ''; form.year = '';
            subjectSearch.value = ''; subjectLabel.value = '';
            objectSearch.value = '';  objectLabel.value = '';
        },
    });
}

// ── Delete ───────────────────────────────────────────────────────────────────

function remove(id) {
    if (!confirm('Delete this connection?')) return;
    router.delete(`/admin/connections/${id}`, { preserveScroll: true });
}

// ── Type label helper ────────────────────────────────────────────────────────

const typeLabels = {
    toured_with:    'Toured With',
    guest_appearance: 'Guest Appearance',
    tribute_song:   'Tribute Song',
    named_after:    'Named After',
    co_written:     'Co-Written With',
    side_project:   'Side Project Of',
    split_from:     'Split From',
    formed_from:    'Formed From Members Of',
    collaboration:  'Collaboration',
    other:          'Other',
};
</script>

<template>
    <Head title="Connections — Admin" />
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-zinc-100">Connections</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Manual relationships between artists, people, and releases.</p>
        </template>

        <!-- Add form -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6 mb-8">
            <h2 class="text-sm font-semibold text-zinc-300 mb-5">Add Connection</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Subject -->
                <div>
                    <label class="field-label">Subject (From)</label>
                    <div class="flex gap-2 mb-2">
                        <select v-model="form.from_type" @change="clearSubject" class="select-sm">
                            <option value="artist">Artist</option>
                            <option value="person">Person</option>
                        </select>
                    </div>
                    <div class="relative">
                        <input
                            v-model="subjectSearch"
                            @input="searchSubject"
                            type="text"
                            placeholder="Search…"
                            class="field-input"
                            :class="{ 'border-red-700': !form.from_id && subjectSearch }"
                        />
                        <button v-if="form.from_id" @click="clearSubject" class="absolute right-2 top-2 text-zinc-500 hover:text-zinc-300 text-xs">✕</button>
                        <div v-if="subjectResults.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                            <button
                                v-for="r in subjectResults"
                                :key="r.id"
                                @click="selectSubject(r)"
                                class="w-full text-left px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700 transition-colors"
                            >{{ r.name }}</button>
                        </div>
                    </div>
                    <p v-if="form.from_id" class="text-xs text-green-500 mt-1">✓ {{ subjectLabel }}</p>
                </div>

                <!-- Object -->
                <div>
                    <label class="field-label">Object (To)</label>
                    <div class="flex gap-2 mb-2">
                        <select v-model="form.to_type" @change="clearObject" class="select-sm">
                            <option value="artist">Artist</option>
                            <option value="person">Person</option>
                            <option value="release">Release</option>
                        </select>
                    </div>
                    <div class="relative">
                        <input
                            v-model="objectSearch"
                            @input="searchObject"
                            type="text"
                            placeholder="Search…"
                            class="field-input"
                            :class="{ 'border-red-700': !form.to_id && objectSearch }"
                        />
                        <button v-if="form.to_id" @click="clearObject" class="absolute right-2 top-2 text-zinc-500 hover:text-zinc-300 text-xs">✕</button>
                        <div v-if="objectResults.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                            <button
                                v-for="r in objectResults"
                                :key="r.id"
                                @click="selectObject(r)"
                                class="w-full text-left px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700 transition-colors"
                            >{{ r.name }}</button>
                        </div>
                    </div>
                    <p v-if="form.to_id" class="text-xs text-green-500 mt-1">✓ {{ objectLabel }}</p>
                </div>

                <!-- Type -->
                <div>
                    <label class="field-label">Relationship Type</label>
                    <select v-model="form.type" class="field-input">
                        <option v-for="t in types" :key="t" :value="t">{{ typeLabels[t] ?? t }}</option>
                    </select>
                </div>

                <!-- Year -->
                <div>
                    <label class="field-label">Year (optional)</label>
                    <input v-model="form.year" type="number" min="1900" max="2100" placeholder="e.g. 2007" class="field-input" />
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="field-label">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        placeholder='e.g. "Simone sang lead vocals on The Haunting from Ghost Opera"'
                        class="field-input resize-none"
                    />
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    @click="save"
                    :disabled="!canSave"
                    class="px-5 py-2 bg-red-700 hover:bg-red-600 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
                >
                    Add Connection
                </button>
            </div>
        </div>

        <!-- List -->
        <div v-if="connections.data.length === 0" class="text-center text-zinc-500 py-12">
            No connections yet. Add one above.
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="c in connections.data"
                :key="c.id"
                class="flex items-start gap-4 px-4 py-3 bg-zinc-900 border border-zinc-800 rounded-lg"
            >
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-medium text-zinc-200">{{ c.from_name }}</span>
                        <span class="text-xs px-2 py-0.5 bg-zinc-800 border border-zinc-700 rounded text-zinc-400">{{ typeLabels[c.type] ?? c.type }}</span>
                        <span class="font-medium text-zinc-200">{{ c.to_name }}</span>
                        <span v-if="c.year" class="text-zinc-500">· {{ c.year }}</span>
                    </div>
                    <p v-if="c.description" class="text-xs text-zinc-500 mt-1">{{ c.description }}</p>
                </div>
                <button
                    @click="remove(c.id)"
                    class="text-zinc-600 hover:text-red-400 transition-colors shrink-0 mt-0.5"
                    title="Delete"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="connections.last_page > 1" class="flex justify-center gap-2 mt-6">
            <a
                v-for="link in connections.links"
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

<style scoped>
.field-label { @apply block text-xs font-medium text-zinc-400 mb-1.5; }
.field-input { @apply w-full bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-100 placeholder-zinc-500 px-3 py-2 focus:outline-none focus:border-zinc-500 transition-colors; }
.select-sm   { @apply bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-300 px-2 py-1 focus:outline-none; }
</style>
