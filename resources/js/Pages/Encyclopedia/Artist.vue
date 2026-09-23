<script setup>
import EncyclopediaLayout from '@/Layouts/EncyclopediaLayout.vue';
import ConnectionGraph from '@/Components/ConnectionGraph.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    artist:         Object,
    currentMembers: Array,
    alumni:         Array,
    discography:    Array,
    connections:    Array,
    graph:          Object,
});

const showFullAlumni = ref(false);
const visibleAlumni  = computed(() => showFullAlumni.value ? props.alumni : props.alumni.slice(0, 6));

// Group connections by type for display
const groupedConnections = computed(() => {
    const groups = {};
    for (const c of props.connections) {
        if (!groups[c.type_label]) groups[c.type_label] = [];
        groups[c.type_label].push(c);
    }
    return groups;
});

// Group release credits per release into role buckets
function creditsByRole(credits) {
    const groups = {};
    for (const c of credits) {
        if (!groups[c.role]) groups[c.role] = [];
        groups[c.role].push(c);
    }
    return groups;
}

function departureLabel(reason) {
    return {
        death:          'Died',
        quit:           'Departed',
        fired:          'Left',
        hiatus:         'Hiatus',
        project_ended:  'Project ended',
    }[reason] ?? '';
}

const releaseTypeLabel = {
    album:       'Album',
    ep:          'EP',
    single:      'Single',
    live:        'Live',
    compilation: 'Compilation',
    demo:        'Demo',
};
</script>

<template>
    <Head :title="`${artist.name} — Encyclopedia`" />
    <EncyclopediaLayout>

        <!-- Breadcrumb -->
        <nav class="text-sm text-zinc-500 mb-5">
            <Link href="/encyclopedia" class="hover:text-zinc-300 transition-colors">Encyclopedia</Link>
            <span class="mx-2">/</span>
            <span class="text-zinc-300">{{ artist.name }}</span>
        </nav>

        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-wrap items-start gap-3 mb-2">
                <h1 class="text-4xl font-bold text-white tracking-tight">{{ artist.name }}</h1>
                <div class="flex flex-wrap gap-2 mt-1.5">
                    <Link
                        v-for="g in artist.genres"
                        :key="g.id"
                        :href="`/encyclopedia/genres/${g.slug}`"
                        class="text-xs px-2.5 py-1 rounded-full border transition-colors"
                        :class="g.primary
                            ? 'bg-red-900/40 border-red-700 text-red-300 hover:bg-red-900/60'
                            : 'bg-zinc-800 border-zinc-700 text-zinc-300 hover:border-zinc-500'"
                    >{{ g.name }}</Link>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-zinc-400">
                <span v-if="artist.origin">{{ artist.origin }}</span>
                <span v-if="artist.formed_year">
                    {{ artist.formed_year }}{{ artist.disbanded_year ? `–${artist.disbanded_year}` : '–present' }}
                </span>
                <span v-if="artist.labels.length" class="text-zinc-500">
                    {{ artist.labels.map(l => l.name).join(' · ') }}
                </span>
            </div>
            <p v-if="artist.bio" class="mt-4 text-zinc-300 leading-relaxed max-w-3xl">{{ artist.bio }}</p>
        </div>

        <!-- Connection graph — hero -->
        <section v-if="graph.nodes.length > 1" class="mb-8 -mx-8">
            <ConnectionGraph :nodes="graph.nodes" :edges="graph.edges" hero />
        </section>

        <!-- Two-column body -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Left: Members -->
            <div class="space-y-8">

                <!-- Current Lineup -->
                <section v-if="currentMembers.length" id="lineup">
                    <h2 class="section-heading">Current Lineup</h2>
                    <div class="space-y-1.5">
                        <Link
                            v-for="m in currentMembers"
                            :key="m.id"
                            :href="`/encyclopedia/people/${m.slug}`"
                            class="flex items-center justify-between px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                        >
                            <span class="font-medium text-white group-hover:text-red-400 transition-colors">{{ m.name }}</span>
                            <div class="text-sm text-zinc-500 flex items-center gap-3 shrink-0">
                                <span v-if="m.instruments.length">{{ m.instruments.join(', ') }}</span>
                                <span v-if="m.start_year">since {{ m.start_year }}</span>
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- Former Members -->
                <section v-if="alumni.length" id="alumni">
                    <h2 class="section-heading">Former Members</h2>
                    <div class="space-y-1.5">
                        <Link
                            v-for="m in visibleAlumni"
                            :key="m.id"
                            :href="`/encyclopedia/people/${m.slug}`"
                            class="flex items-center justify-between px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                        >
                            <span class="font-medium text-zinc-300 group-hover:text-red-400 transition-colors">{{ m.name }}</span>
                            <div class="text-sm text-zinc-500 flex items-center gap-3 shrink-0">
                                <span v-if="m.instruments.length">{{ m.instruments.join(', ') }}</span>
                                <span v-if="m.start_year && m.end_year">{{ m.start_year }}–{{ m.end_year }}</span>
                                <span v-if="m.departure_reason" class="text-zinc-600 text-xs">{{ departureLabel(m.departure_reason) }}</span>
                            </div>
                        </Link>
                        <button
                            v-if="alumni.length > 6 && !showFullAlumni"
                            @click="showFullAlumni = true"
                            class="text-sm text-zinc-500 hover:text-zinc-300 transition-colors mt-1 px-4"
                        >Show all {{ alumni.length }} former members ↓</button>
                    </div>
                </section>

                <!-- Influences (belongs near members conceptually) -->
                <section v-if="artist.influences.length || artist.influenced.length">
                    <h2 class="section-heading">Influences</h2>
                    <div class="space-y-4">
                        <div v-if="artist.influences.length">
                            <p class="text-xs text-zinc-600 uppercase tracking-wide mb-2">Influenced by</p>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="a in artist.influences"
                                    :key="a.id"
                                    :href="`/encyclopedia/artists/${a.slug}`"
                                    class="text-sm px-3 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-600 text-zinc-300 hover:text-white rounded-lg transition-colors"
                                >{{ a.name }}</Link>
                            </div>
                        </div>
                        <div v-if="artist.influenced.length">
                            <p class="text-xs text-zinc-600 uppercase tracking-wide mb-2">Influenced</p>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="a in artist.influenced"
                                    :key="a.id"
                                    :href="`/encyclopedia/artists/${a.slug}`"
                                    class="text-sm px-3 py-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-600 text-zinc-300 hover:text-white rounded-lg transition-colors"
                                >{{ a.name }}</Link>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

            <!-- Right: Discography -->
            <div>
                <section v-if="discography.length" id="discography">
                    <h2 class="section-heading">Discography</h2>
                    <div class="space-y-2">
                        <div
                            v-for="r in discography"
                            :key="r.id"
                            class="px-4 py-3 bg-zinc-900 border border-zinc-800 hover:border-zinc-700 rounded-lg transition-colors group"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <Link :href="`/encyclopedia/releases/${r.slug}`" class="font-semibold text-white hover:text-red-400 transition-colors">{{ r.title }}</Link>
                                    <span class="ml-2 text-xs px-1.5 py-0.5 bg-zinc-800 text-zinc-400 rounded">
                                        {{ releaseTypeLabel[r.type] ?? r.type }}
                                    </span>
                                </div>
                                <span class="text-sm text-zinc-500 shrink-0">{{ r.year }}</span>
                            </div>
                            <div v-if="r.credits.length" class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5">
                                <template v-for="(people, role) in creditsByRole(r.credits)" :key="role">
                                    <span class="text-xs text-zinc-500">
                                        <span class="capitalize">{{ role }}</span>:
                                        <template v-for="(c, i) in people" :key="c.person_id">
                                            <Link :href="`/encyclopedia/people/${c.person_slug}`" class="text-zinc-400 hover:text-red-400 transition-colors">{{ c.person_name }}</Link><span v-if="i < people.length - 1">, </span>
                                        </template>
                                    </span>
                                </template>
                            </div>
                            <div v-if="r.labels.length" class="mt-0.5 text-xs text-zinc-600">{{ r.labels.join(' · ') }}</div>
                        </div>
                    </div>
                </section>
            </div>

        </div>

        <!-- Connections — full width below the columns -->
        <section v-if="connections.length" id="connections" class="mt-10">
            <h2 class="section-heading">Connections</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <template v-for="(items, label) in groupedConnections" :key="label">
                    <div
                        v-for="c in items"
                        :key="c.id"
                        class="px-4 py-3 bg-zinc-900 border border-zinc-800 rounded-lg"
                    >
                        <p class="text-xs text-zinc-600 uppercase tracking-wide mb-1">{{ label }}</p>
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
