<script setup>
import EncyclopediaLayout from '@/Layouts/EncyclopediaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    release: Object,
    tracks:  Array,
});

const releaseTypeLabel = {
    album:       'Album',
    ep:          'EP',
    single:      'Single',
    live:        'Live',
    compilation: 'Compilation',
    demo:        'Demo',
};

// Group tracks by disc for multi-disc releases
const discs = computed(() => {
    const groups = {};
    for (const t of props.tracks) {
        const d = t.disc || 1;
        if (!groups[d]) groups[d] = [];
        groups[d].push(t);
    }
    return groups;
});

const multiDisc = computed(() => Object.keys(discs.value).length > 1);

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
    <Head :title="`${release.title} — Encyclopedia`" />
    <EncyclopediaLayout>

        <!-- Breadcrumb -->
        <nav class="text-sm text-zinc-500 mb-5">
            <Link href="/encyclopedia" class="hover:text-zinc-300 transition-colors">Encyclopedia</Link>
            <span class="mx-2">/</span>
            <Link :href="`/encyclopedia/artists/${release.artist.slug}`" class="hover:text-zinc-300 transition-colors">{{ release.artist.name }}</Link>
            <span class="mx-2">/</span>
            <span class="text-zinc-300">{{ release.title }}</span>
        </nav>

        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-wrap items-start gap-3 mb-2">
                <h1 class="text-4xl font-bold text-white tracking-tight">{{ release.title }}</h1>
                <span class="text-xs px-2.5 py-1 mt-1.5 bg-zinc-800 text-zinc-400 rounded-full">
                    {{ releaseTypeLabel[release.type] ?? release.type }}
                </span>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-zinc-400">
                <Link :href="`/encyclopedia/artists/${release.artist.slug}`" class="text-white hover:text-red-400 transition-colors font-medium">{{ release.artist.name }}</Link>
                <span v-if="release.year">{{ release.year }}</span>
                <span v-if="release.catalog_number" class="text-zinc-500">{{ release.catalog_number }}</span>
                <span v-if="release.labels.length" class="text-zinc-500">{{ release.labels.join(' · ') }}</span>
            </div>
            <div v-if="release.genres.length" class="flex flex-wrap gap-2 mt-3">
                <Link
                    v-for="g in release.genres"
                    :key="g.slug"
                    :href="`/encyclopedia/genres/${g.slug}`"
                    class="text-xs px-2.5 py-1 rounded-full border bg-zinc-800 border-zinc-700 text-zinc-300 hover:border-zinc-500 transition-colors"
                >{{ g.name }}</Link>
            </div>
            <p v-if="release.description" class="mt-4 text-zinc-300 leading-relaxed max-w-3xl">{{ release.description }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Tracklist -->
            <div class="lg:col-span-2">
                <section id="tracklist">
                    <h2 class="section-heading">Tracklist</h2>
                    <div v-for="(discTracks, disc) in discs" :key="disc" class="mb-4">
                        <p v-if="multiDisc" class="text-xs text-zinc-600 uppercase tracking-wide mb-2">Disc {{ disc }}</p>
                        <div class="space-y-1">
                            <Link
                                v-for="t in discTracks"
                                :key="t.id"
                                :href="`/encyclopedia/songs/${t.song_id}`"
                                class="flex items-center justify-between px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="text-sm text-zinc-600 w-5 text-right shrink-0">{{ t.position }}</span>
                                    <span class="font-medium text-white group-hover:text-red-400 transition-colors truncate">{{ t.song_title }}</span>
                                    <span v-if="t.is_cover" class="text-xs px-1.5 py-0.5 bg-zinc-800 text-zinc-500 rounded shrink-0">
                                        cover<template v-if="t.original_artist_name">, orig. {{ t.original_artist_name }}</template>
                                    </span>
                                </div>
                                <span v-if="t.duration_formatted" class="text-sm text-zinc-500 shrink-0">{{ t.duration_formatted }}</span>
                            </Link>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Credits -->
            <div>
                <section v-if="release.credits.length" id="credits">
                    <h2 class="section-heading">Credits</h2>
                    <div class="space-y-3">
                        <template v-for="(people, role) in creditsByRole(release.credits)" :key="role">
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
            </div>

        </div>

    </EncyclopediaLayout>
</template>

<style scoped>
.section-heading {
    @apply text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4 pb-2 border-b border-zinc-800;
}
</style>
