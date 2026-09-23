<script setup>
import EncyclopediaLayout from '@/Layouts/EncyclopediaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    genre:   Object,
    artists: Array,
});

// Separate primary genre artists from secondary
const primary   = computed(() => props.artists.filter(a => a.is_primary));
const secondary = computed(() => props.artists.filter(a => !a.is_primary));
</script>

<template>
    <Head :title="`${genre.name} — Encyclopedia`" />
    <EncyclopediaLayout>

        <!-- Breadcrumb -->
        <nav class="text-sm text-zinc-500 mb-6">
            <Link href="/encyclopedia" class="hover:text-zinc-300 transition-colors">Encyclopedia</Link>
            <span class="mx-2">/</span>
            <span class="text-zinc-300">{{ genre.name }}</span>
        </nav>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-white tracking-tight mb-2">{{ genre.name }}</h1>
            <p v-if="genre.description" class="text-zinc-400 max-w-2xl">{{ genre.description }}</p>
            <p class="text-sm text-zinc-500 mt-1">{{ artists.length }} artist{{ artists.length !== 1 ? 's' : '' }}</p>
        </div>

        <!-- Primary artists (this is their main genre) -->
        <section v-if="primary.length" class="mb-10">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4">Core {{ genre.name }} Artists</h2>
            <div class="grid gap-2">
                <Link
                    v-for="a in primary"
                    :key="a.id"
                    :href="`/encyclopedia/artists/${a.slug}`"
                    class="flex items-center justify-between px-4 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                >
                    <span class="font-semibold text-white group-hover:text-red-400 transition-colors">{{ a.name }}</span>
                    <div class="flex items-center gap-4 text-sm text-zinc-500 shrink-0">
                        <span v-if="a.origin">{{ a.origin }}</span>
                        <span v-if="a.formed_year">
                            {{ a.formed_year }}{{ a.disbanded_year ? `–${a.disbanded_year}` : '–present' }}
                        </span>
                        <span v-if="a.releases_count">{{ a.releases_count }} release{{ a.releases_count !== 1 ? 's' : '' }}</span>
                        <svg class="w-4 h-4 text-zinc-600 group-hover:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="m9 18 6-6-6-6"/>
                        </svg>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Secondary (genre is not their primary) -->
        <section v-if="secondary.length">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4">Also Tagged {{ genre.name }}</h2>
            <div class="grid gap-2">
                <Link
                    v-for="a in secondary"
                    :key="a.id"
                    :href="`/encyclopedia/artists/${a.slug}`"
                    class="flex items-center justify-between px-4 py-3 bg-zinc-900/60 hover:bg-zinc-800 border border-zinc-800/60 hover:border-zinc-700 rounded-lg transition-colors group"
                >
                    <span class="font-medium text-zinc-300 group-hover:text-red-400 transition-colors">{{ a.name }}</span>
                    <div class="flex items-center gap-4 text-sm text-zinc-500 shrink-0">
                        <span v-if="a.origin">{{ a.origin }}</span>
                        <span v-if="a.formed_year">
                            {{ a.formed_year }}{{ a.disbanded_year ? `–${a.disbanded_year}` : '' }}
                        </span>
                        <svg class="w-4 h-4 text-zinc-700 group-hover:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="m9 18 6-6-6-6"/>
                        </svg>
                    </div>
                </Link>
            </div>
        </section>

    </EncyclopediaLayout>
</template>
