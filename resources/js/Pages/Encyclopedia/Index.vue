<script setup>
import EncyclopediaLayout from '@/Layouts/EncyclopediaLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    query:   String,
    artists: Array,
    people:  Array,
});

const q = ref(props.query ?? '');

function search() {
    router.get('/encyclopedia', { q: q.value.trim() }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Encyclopedia — Nexus Radio" />
    <EncyclopediaLayout>

        <!-- Hero / search -->
        <div class="text-center py-16">
            <h1 class="text-4xl font-bold tracking-tight text-white mb-2">Metal Encyclopedia</h1>
            <p class="text-zinc-400 mb-8">Explore bands, people, and the connections between them.</p>

            <form @submit.prevent="search" class="flex gap-2 max-w-lg mx-auto">
                <input
                    v-model="q"
                    type="text"
                    placeholder="Search for a band or person…"
                    autofocus
                    class="flex-1 bg-zinc-800 border border-zinc-700 rounded-lg text-zinc-100 placeholder-zinc-500 px-4 py-3 focus:outline-none focus:border-zinc-500 transition-colors text-base"
                />
                <button
                    type="submit"
                    class="px-6 py-3 bg-red-600 hover:bg-red-500 text-white font-medium rounded-lg transition-colors text-sm"
                >
                    Search
                </button>
            </form>
        </div>

        <!-- Results -->
        <template v-if="query">
            <div v-if="artists.length === 0 && people.length === 0" class="text-center text-zinc-500 py-12">
                No results for <span class="text-zinc-300">"{{ query }}"</span>
            </div>

            <div v-else class="space-y-10">

                <!-- Artists -->
                <section v-if="artists.length">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4">Bands &amp; Artists</h2>
                    <div class="grid gap-2">
                        <Link
                            v-for="a in artists"
                            :key="a.id"
                            :href="`/encyclopedia/artists/${a.slug}`"
                            class="flex items-center gap-4 px-4 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                        >
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-white group-hover:text-red-400 transition-colors">{{ a.name }}</span>
                                <span v-if="a.genre" class="ml-2 text-sm text-zinc-400">{{ a.genre }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-zinc-500 shrink-0">
                                <span v-if="a.origin">{{ a.origin }}</span>
                                <span v-if="a.formed_year">
                                    {{ a.formed_year }}{{ a.disbanded_year ? `–${a.disbanded_year}` : '–present' }}
                                </span>
                                <span v-if="a.releases_count">{{ a.releases_count }} release{{ a.releases_count !== 1 ? 's' : '' }}</span>
                                <svg class="w-4 h-4 text-zinc-600 group-hover:text-zinc-400 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="m9 18 6-6-6-6"/>
                                </svg>
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- People -->
                <section v-if="people.length">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4">People</h2>
                    <div class="grid gap-2">
                        <Link
                            v-for="p in people"
                            :key="p.id"
                            :href="`/encyclopedia/people/${p.slug}`"
                            class="flex items-center gap-4 px-4 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                        >
                            <div class="flex-1">
                                <span class="font-semibold text-white group-hover:text-red-400 transition-colors">{{ p.name }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-zinc-500">
                                <span v-if="p.bands">{{ p.bands }} band{{ p.bands !== 1 ? 's' : '' }}</span>
                                <svg class="w-4 h-4 text-zinc-600 group-hover:text-zinc-400 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="m9 18 6-6-6-6"/>
                                </svg>
                            </div>
                        </Link>
                    </div>
                </section>

            </div>
        </template>

        <!-- Landing hints when no query -->
        <template v-else>
            <div class="text-center text-zinc-600 text-sm mt-4">
                Try searching for <span class="text-zinc-400 cursor-pointer hover:text-red-400 transition-colors" @click="q = 'Epica'; search()">Epica</span>,
                <span class="text-zinc-400 cursor-pointer hover:text-red-400 transition-colors" @click="q = 'Kamelot'; search()">Kamelot</span>,
                or <span class="text-zinc-400 cursor-pointer hover:text-red-400 transition-colors" @click="q = 'Simone'; search()">Simone</span>
            </div>
        </template>

    </EncyclopediaLayout>
</template>
