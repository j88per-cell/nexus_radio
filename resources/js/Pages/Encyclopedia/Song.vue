<script setup>
import EncyclopediaLayout from '@/Layouts/EncyclopediaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    song:         Object,
    appearances:  Array,
});

const releaseTypeLabel = {
    album:       'Album',
    ep:          'EP',
    single:      'Single',
    live:        'Live',
    compilation: 'Compilation',
    demo:        'Demo',
};

// First chronological appearance is treated as the "home" release;
// everything else (compilations, live albums, reissues) is a sibling appearance.
const primary = computed(() => props.appearances[0] ?? null);
const others  = computed(() => props.appearances.slice(1));

function creditsByRole(credits) {
    const groups = {};
    for (const c of credits) {
        if (!groups[c.role]) groups[c.role] = [];
        groups[c.role].push(c);
    }
    return groups;
}
</script>

<template>
    <Head :title="`${song.title} — Encyclopedia`" />
    <EncyclopediaLayout>

        <!-- Breadcrumb -->
        <nav class="text-sm text-zinc-500 mb-5">
            <Link href="/encyclopedia" class="hover:text-zinc-300 transition-colors">Encyclopedia</Link>
            <template v-if="primary">
                <span class="mx-2">/</span>
                <Link :href="`/encyclopedia/artists/${primary.artist_slug}`" class="hover:text-zinc-300 transition-colors">{{ primary.artist_name }}</Link>
                <span class="mx-2">/</span>
                <Link :href="`/encyclopedia/releases/${primary.release_slug}`" class="hover:text-zinc-300 transition-colors">{{ primary.release_title }}</Link>
            </template>
            <span class="mx-2">/</span>
            <span class="text-zinc-300">{{ song.title }}</span>
        </nav>

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-4xl font-bold text-white tracking-tight mb-2">{{ song.title }}</h1>
            <div class="flex flex-wrap gap-4 text-sm text-zinc-400">
                <span v-if="song.mood" class="text-purple-400">{{ song.mood }}</span>
                <span v-if="song.original_artist" class="text-zinc-500">
                    originally by
                    <Link :href="`/encyclopedia/artists/${song.original_artist.slug}`" class="text-zinc-300 hover:text-red-400 transition-colors">{{ song.original_artist.name }}</Link>
                </span>
            </div>
            <p v-if="song.song_meaning" class="mt-4 text-zinc-300 leading-relaxed max-w-3xl">{{ song.song_meaning }}</p>
            <p v-if="song.story" class="mt-2 text-zinc-400 leading-relaxed max-w-3xl text-sm">{{ song.story }}</p>
        </div>

        <!-- Home release, shown above like the parent in a tree -->
        <section v-if="primary" class="mb-8">
            <h2 class="section-heading">From</h2>
            <Link
                :href="`/encyclopedia/releases/${primary.release_slug}`"
                class="flex items-center justify-between px-4 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group max-w-md"
            >
                <div class="min-w-0">
                    <span class="font-semibold text-white group-hover:text-red-400 transition-colors">{{ primary.release_title }}</span>
                    <span class="ml-2 text-xs px-1.5 py-0.5 bg-zinc-800 text-zinc-400 rounded">{{ releaseTypeLabel[primary.release_type] ?? primary.release_type }}</span>
                    <div class="text-sm text-zinc-500">{{ primary.artist_name }}<span v-if="primary.year"> · {{ primary.year }}</span></div>
                </div>
            </Link>
        </section>

        <!-- Other appearances — siblings alongside this song across the catalog -->
        <section v-if="others.length" class="mb-8">
            <h2 class="section-heading">Also Appears On</h2>
            <div class="flex gap-3 overflow-x-auto pb-2">
                <Link
                    v-for="a in others"
                    :key="a.track_id"
                    :href="`/encyclopedia/releases/${a.release_slug}`"
                    class="shrink-0 w-56 px-4 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                >
                    <span class="font-medium text-white group-hover:text-red-400 transition-colors block truncate">{{ a.release_title }}</span>
                    <span class="text-xs px-1.5 py-0.5 bg-zinc-800 text-zinc-400 rounded inline-block mt-1">{{ releaseTypeLabel[a.release_type] ?? a.release_type }}</span>
                    <div class="text-sm text-zinc-500 mt-1 truncate">{{ a.artist_name }}<span v-if="a.year"> · {{ a.year }}</span></div>
                </Link>
            </div>
        </section>

        <!-- Credits -->
        <section v-if="song.credits.length">
            <h2 class="section-heading">Credits</h2>
            <div class="space-y-3">
                <template v-for="(people, role) in creditsByRole(song.credits)" :key="role">
                    <div>
                        <p class="text-xs text-zinc-600 uppercase tracking-wide mb-1 capitalize">{{ role }}</p>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="c in people"
                                :key="c.person_id"
                                :href="`/encyclopedia/people/${c.person_slug}`"
                                class="text-sm px-3 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-600 text-zinc-300 hover:text-white rounded-lg transition-colors"
                            >{{ c.person_name }}</Link>
                        </div>
                    </div>
                </template>
            </div>
        </section>

    </EncyclopediaLayout>
</template>

<style scoped>
.section-heading {
    @apply text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4 pb-2 border-b border-zinc-800;
}
</style>
