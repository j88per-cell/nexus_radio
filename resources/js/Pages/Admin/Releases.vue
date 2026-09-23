<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    releases: Object,
    filters:  Object,
});

const search = ref(props.filters?.search ?? '');
const artist = ref(props.filters?.artist ?? '');
const filter = ref(props.filters?.filter ?? '');

let searchTimer = null;

function applyFilters() {
    router.get('/admin/releases', {
        search: search.value || undefined,
        artist: artist.value || undefined,
        filter: filter.value || undefined,
    }, { preserveState: true, replace: true });
}

watch([search, artist, filter], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

function toggleBlocked(release) {
    router.post(`/admin/releases/${release.id}/toggle-blocked`, {}, { preserveState: true });
}

function typeLabel(type) {
    return {
        album:  'Album',
        single: 'Single',
        ep:     'EP',
        live:   'Live',
        comp:   'Comp.',
    }[type] ?? type ?? '—';
}
</script>

<template>
    <Head title="Releases" />
    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-zinc-100">Releases</h1>
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
                    <option value="">All releases</option>
                    <option value="blocked">Blocked</option>
                </select>
                <span class="text-xs text-zinc-600 ml-auto">{{ releases.total }} releases</span>
            </div>

            <!-- Table -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-700 text-xs uppercase tracking-wider text-zinc-500">
                            <th class="text-left px-4 py-2.5 font-medium">Title</th>
                            <th class="text-left px-4 py-2.5 font-medium">Artist</th>
                            <th class="text-left px-4 py-2.5 font-medium w-20">Type</th>
                            <th class="text-left px-4 py-2.5 font-medium w-16">Year</th>
                            <th class="px-4 py-2.5 w-36"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <tr
                            v-for="release in releases.data"
                            :key="release.id"
                            class="hover:bg-zinc-800/40 transition-colors"
                            :class="{ 'opacity-50': release.blocked }"
                        >
                            <td class="px-4 py-3 font-mono text-xs" :class="release.blocked ? 'text-red-400 line-through' : 'text-zinc-200'">
                                <a :href="`/encyclopedia/releases/${release.slug}`" target="_blank" class="hover:text-red-400 transition-colors">{{ release.title }}</a>
                            </td>
                            <td class="px-4 py-3 text-zinc-400 text-xs">{{ release.artist ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-500 text-xs">{{ typeLabel(release.type) }}</td>
                            <td class="px-4 py-3 text-zinc-500 text-xs">{{ release.release_date ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        :href="`/encyclopedia/releases/${release.slug}`"
                                        target="_blank"
                                        class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-500 hover:text-zinc-300 transition-colors"
                                        title="View in encyclopedia"
                                    >view</a>
                                    <button
                                        @click="toggleBlocked(release)"
                                        class="text-xs px-2 py-1 rounded transition-colors"
                                        :class="release.blocked
                                            ? 'bg-red-900/40 hover:bg-red-900/60 text-red-400 hover:text-red-300'
                                            : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-500 hover:text-red-400'"
                                        :title="release.blocked ? 'Unblock album' : 'Block album from playing'"
                                    >
                                        {{ release.blocked ? 'unblock' : 'block' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="releases.last_page > 1" class="flex items-center justify-between text-xs text-zinc-500">
                <span>Page {{ releases.current_page }} of {{ releases.last_page }}</span>
                <div class="flex gap-2">
                    <a
                        v-if="releases.prev_page_url"
                        :href="releases.prev_page_url"
                        class="px-3 py-1.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition-colors"
                    >← Prev</a>
                    <a
                        v-if="releases.next_page_url"
                        :href="releases.next_page_url"
                        class="px-3 py-1.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition-colors"
                    >Next →</a>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
