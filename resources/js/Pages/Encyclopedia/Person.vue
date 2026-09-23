<script setup>
import EncyclopediaLayout from '@/Layouts/EncyclopediaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    person:             Object,
    memberships:        Array,
    productionCredits:  Array,
    songCredits:        Array,
    connections:        Array,
});

const currentBands = computed(() => props.memberships.filter(m => m.is_current));
const formerBands  = computed(() => props.memberships.filter(m => !m.is_current));

// Group connections by type label
const groupedConnections = computed(() => {
    const groups = {};
    for (const c of props.connections) {
        if (!groups[c.type_label]) groups[c.type_label] = [];
        groups[c.type_label].push(c);
    }
    return groups;
});

function departureLabel(reason) {
    return {
        death:         'Died',
        quit:          'Departed',
        fired:         'Left',
        hiatus:        'On hiatus',
        project_ended: 'Project ended',
    }[reason] ?? '';
}
</script>

<template>
    <Head :title="`${person.name} — Encyclopedia`" />
    <EncyclopediaLayout>

        <!-- Breadcrumb -->
        <nav class="text-sm text-zinc-500 mb-6">
            <Link href="/encyclopedia" class="hover:text-zinc-300 transition-colors">Encyclopedia</Link>
            <span class="mx-2">/</span>
            <span class="text-zinc-300">{{ person.name }}</span>
        </nav>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-white tracking-tight mb-2">{{ person.name }}</h1>
            <div class="flex flex-wrap gap-3 text-sm text-zinc-400">
                <span v-if="person.primary_instrument" class="text-zinc-300">{{ person.primary_instrument }}</span>
                <span v-if="person.born">b. {{ person.born }}</span>
                <span v-if="person.died" class="text-zinc-500">d. {{ person.died }}</span>
                <span v-if="currentBands.length">
                    currently in
                    <span v-for="(b, i) in currentBands" :key="b.artist_id">
                        <Link :href="`/encyclopedia/artists/${b.artist_slug}`" class="text-zinc-300 hover:text-red-400 transition-colors">{{ b.artist_name }}</Link><span v-if="i < currentBands.length - 1">, </span>
                    </span>
                </span>
            </div>
        </div>

        <!-- Bio / Story -->
        <div v-if="person.bio || person.story" class="mb-10 max-w-3xl space-y-4">
            <p v-if="person.bio" class="text-zinc-300 leading-relaxed">{{ person.bio }}</p>
            <p v-if="person.story" class="text-zinc-400 leading-relaxed italic border-l-2 border-zinc-700 pl-4">{{ person.story }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-10">

                <!-- Current bands -->
                <section v-if="currentBands.length">
                    <h2 class="section-heading">Current Bands</h2>
                    <div class="space-y-3">
                        <div
                            v-for="m in currentBands"
                            :key="m.artist_id"
                            class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden"
                        >
                            <Link
                                :href="`/encyclopedia/artists/${m.artist_slug}`"
                                class="flex items-center justify-between px-4 py-3 hover:bg-zinc-800 transition-colors group"
                            >
                                <div>
                                    <span class="font-semibold text-white group-hover:text-red-400 transition-colors">{{ m.artist_name }}</span>
                                    <span v-if="m.artist_genre" class="ml-2 text-sm text-zinc-500">{{ m.artist_genre }}</span>
                                </div>
                                <div class="text-sm text-zinc-500 flex gap-3">
                                    <span v-if="m.instruments.length">{{ m.instruments.join(', ') }}</span>
                                    <span v-if="m.start_year">since {{ m.start_year }}</span>
                                </div>
                            </Link>
                            <div v-if="m.releases.length" class="px-4 pb-3 flex flex-wrap gap-2 border-t border-zinc-800 pt-2.5">
                                <Link
                                    v-for="r in m.releases"
                                    :key="r.id"
                                    :href="`/encyclopedia/artists/${m.artist_slug}#discography`"
                                    class="text-xs px-2 py-1 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 text-zinc-300 hover:text-white rounded transition-colors"
                                >{{ r.title }} <span class="text-zinc-500">{{ r.year }}</span></Link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Former bands -->
                <section v-if="formerBands.length">
                    <h2 class="section-heading">Former Bands</h2>
                    <div class="space-y-3">
                        <div
                            v-for="m in formerBands"
                            :key="m.artist_id"
                            class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden"
                        >
                            <Link
                                :href="`/encyclopedia/artists/${m.artist_slug}`"
                                class="flex items-center justify-between px-4 py-3 hover:bg-zinc-800 transition-colors group"
                            >
                                <div>
                                    <span class="font-medium text-zinc-200 group-hover:text-red-400 transition-colors">{{ m.artist_name }}</span>
                                    <span v-if="m.artist_genre" class="ml-2 text-sm text-zinc-500">{{ m.artist_genre }}</span>
                                </div>
                                <div class="text-sm text-zinc-500 flex gap-3 items-center">
                                    <span v-if="m.instruments.length">{{ m.instruments.join(', ') }}</span>
                                    <span v-if="m.start_year">{{ m.start_year }}{{ m.end_year ? `–${m.end_year}` : '' }}</span>
                                    <span v-if="m.departure_reason" class="text-zinc-600 text-xs">{{ departureLabel(m.departure_reason) }}</span>
                                </div>
                            </Link>
                            <div v-if="m.releases.length" class="px-4 pb-3 flex flex-wrap gap-2 border-t border-zinc-800 pt-2.5">
                                <Link
                                    v-for="r in m.releases"
                                    :key="r.id"
                                    :href="`/encyclopedia/artists/${m.artist_slug}#discography`"
                                    class="text-xs px-2 py-1 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 text-zinc-300 hover:text-white rounded transition-colors"
                                >{{ r.title }} <span class="text-zinc-500">{{ r.year }}</span></Link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Production Credits -->
                <section v-if="productionCredits.length">
                    <h2 class="section-heading">Production Credits</h2>
                    <div class="space-y-6">
                        <div v-for="group in productionCredits" :key="group.role">
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-3 capitalize">{{ group.role }}</h3>
                            <div class="space-y-1.5">
                                <Link
                                    v-for="r in group.releases"
                                    :key="r.id"
                                    :href="`/encyclopedia/artists/${r.artist_slug}`"
                                    class="flex items-center justify-between px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                                >
                                    <div class="min-w-0">
                                        <span class="text-zinc-200 group-hover:text-red-400 transition-colors font-medium">{{ r.artist_name }}</span>
                                        <span class="text-zinc-500 mx-2">—</span>
                                        <span class="text-zinc-300">{{ r.title }}</span>
                                    </div>
                                    <span v-if="r.year" class="text-sm text-zinc-500 shrink-0 ml-3">{{ r.year }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Connections (guest appearances, tributes, etc.) -->
                <section v-if="connections.length">
                    <h2 class="section-heading">Connections</h2>
                    <div class="space-y-4">
                        <div v-for="(items, label) in groupedConnections" :key="label">
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-2">{{ label }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="c in items"
                                    :key="c.id"
                                    class="px-4 py-3 bg-zinc-900 border border-zinc-800 rounded-lg"
                                >
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="c.entity_href !== '#'"
                                            :href="c.entity_href"
                                            class="font-medium text-white hover:text-red-400 transition-colors"
                                        >{{ c.entity_name }}</Link>
                                        <span v-else class="font-medium text-white">{{ c.entity_name }}</span>
                                        <span v-if="c.year" class="text-xs text-zinc-500">{{ c.year }}</span>
                                    </div>
                                    <p v-if="c.description" class="text-sm text-zinc-400 mt-1">{{ c.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

            <!-- Sidebar: writing credits -->
            <div class="space-y-8">
                <section v-if="songCredits.length">
                    <h3 class="sidebar-heading">Song Credits</h3>
                    <div class="space-y-3">
                        <div v-for="(credits, role) in Object.groupBy ? Object.groupBy(songCredits, c => c.role) : groupByRole(songCredits)" :key="role">
                            <p class="text-xs text-zinc-600 uppercase tracking-wide mb-1 capitalize">{{ role }}</p>
                            <div class="space-y-1">
                                <p v-for="c in credits" :key="c.song_id" class="text-sm text-zinc-400">{{ c.song_title }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

        </div>

    </EncyclopediaLayout>
</template>

<script>
// Fallback group-by for browsers without Object.groupBy
function groupByRole(credits) {
    return credits.reduce((acc, c) => {
        if (!acc[c.role]) acc[c.role] = [];
        acc[c.role].push(c);
        return acc;
    }, {});
}
</script>

<style scoped>
.section-heading {
    @apply text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-4 pb-2 border-b border-zinc-800;
}
.sidebar-heading {
    @apply text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-3;
}
</style>
