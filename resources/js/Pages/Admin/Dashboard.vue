<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RequestSongModal from '@/Components/RequestSongModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    nowPlaying: Object,
    queue:      Array,
    health:     Object,
    stats:      Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Auto-refresh every 15s
let refreshTimer = null;
let clockTimer   = null;

onMounted(() => {
    refreshTimer = setInterval(() => router.reload({ only: ['nowPlaying', 'queue', 'health'] }), 15000);
    tickClock();
    clockTimer = setInterval(tickClock, 1000);
});
onUnmounted(() => {
    clearInterval(refreshTimer);
    clearInterval(clockTimer);
});

// ─── Clock ───────────────────────────────────────────────────────────────────

const clockDisplay = ref('');

function tickClock() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    const day = d.toLocaleDateString('en-US', { weekday: 'short' }).toUpperCase();
    clockDisplay.value = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())} · ${day}`;
}

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

const firstName = computed(() => {
    const name = user.value?.name;
    if (!name) return 'there';
    return name.split(' ')[0];
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDuration(secs) {
    if (!secs) return '—';
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function timeAgo(ts) {
    if (!ts) return 'never';
    const diff = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (diff < 60)    return `${diff}s ago`;
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

function trackInitial(title) {
    if (!title) return '♪';
    return title[0].toUpperCase();
}

// ─── Waveform ────────────────────────────────────────────────────────────────

const waveHeights = (() => {
    const bars = [];
    for (let i = 0; i < 80; i++) {
        const v = 0.25 + Math.abs(
            Math.sin(i * 0.42) * 0.5 +
            Math.sin(i * 0.13) * 0.3 +
            Math.sin(i * 0.91) * 0.15
        );
        bars.push(Math.max(8, Math.min(96, Math.round(v * 70))));
    }
    return bars;
})();

// ─── Album art gradients (cycling) ───────────────────────────────────────────

const ART_BG = [
    'radial-gradient(circle at 30% 25%, #4a2010, #200c05)',
    'radial-gradient(circle at 30% 25%, #3a2800, #1a1200)',
    'radial-gradient(circle at 30% 25%, #1a1840, #0c0b20)',
    'radial-gradient(circle at 30% 25%, #143020, #081810)',
    'radial-gradient(circle at 30% 25%, #3a1028, #1c0814)',
    'radial-gradient(circle at 30% 25%, #102838, #08141c)',
];

function artStyle(idx) {
    return { background: ART_BG[idx % ART_BG.length] };
}

// Now-playing art: always second gradient (warm gold-brown)
const nowPlayingArtStyle = { background: ART_BG[1] };

// ─── Song Request ────────────────────────────────────────────────────────────

const requestModalOpen = ref(false);
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout>
        <div class="space-y-4">

            <!-- ── Topbar ─────────────────────────────────────────────────── -->
            <div class="flex items-end gap-4 pb-1">
                <div>
                    <div class="font-mono text-[11px] tracking-[.14em] uppercase text-zinc-500">
                        <span class="text-zinc-300 font-medium">Station</span> · Dashboard
                    </div>
                    <h1 class="text-[26px] font-semibold leading-none tracking-tight mt-1.5">
                        {{ greeting }},&nbsp;<span class="text-brass">{{ firstName }}</span>
                    </h1>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <button
                        @click="requestModalOpen = true"
                        class="font-mono text-[11px] tracking-[.08em] uppercase px-3 py-1.5 rounded-full bg-brass/90 hover:bg-brass text-zinc-950 font-semibold transition-colors"
                    >Request</button>
                    <span class="font-mono text-[11.5px] tracking-[.06em] text-zinc-400 px-3 py-1.5 border border-zinc-800 rounded-full bg-zinc-900/80">
                        {{ clockDisplay }}
                    </span>
                    <span class="flex items-center gap-2 font-mono text-[11px] tracking-[.14em] uppercase px-2.5 py-1.5 border rounded-full bg-zinc-900/80"
                        :class="health?.liquidsoap_connected
                            ? 'border-zinc-800 text-zinc-400'
                            : 'border-zinc-800 text-zinc-600'"
                    >
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="health?.liquidsoap_connected ? 'bg-red-500 animate-pulse' : 'bg-zinc-700'"
                        />
                        {{ health?.liquidsoap_connected ? 'On Air' : 'Off Air' }}
                    </span>
                </div>
            </div>

            <!-- ── Row 1: Now Playing + Health ────────────────────────────── -->
            <div class="grid gap-4" style="grid-template-columns: 1.6fr 1fr;">

                <!-- Now Playing hero card -->
                <div class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden flex" style="grid-template-columns: 200px 1fr;">

                    <!-- Album art -->
                    <div
                        class="relative w-[200px] shrink-0 flex items-center justify-center overflow-hidden"
                        :style="nowPlayingArtStyle"
                    >
                        <!-- Diagonal stripe overlay -->
                        <div
                            class="absolute inset-0 opacity-[.04] mix-blend-overlay"
                            style="background: repeating-linear-gradient(135deg, transparent 0 14px, white 14px 15px);"
                        />
                        <!-- Letter glyph -->
                        <span class="relative font-serif italic text-[64px] font-bold opacity-80 select-none"
                            style="color: oklch(0.85 0.10 75); text-shadow: 0 2px 12px rgba(0,0,0,.4);">
                            {{ trackInitial(nowPlaying?.title) }}
                        </span>
                        <!-- "Now Playing" corner chip -->
                        <div class="absolute top-2.5 left-2.5 font-mono text-[10px] tracking-[.14em] uppercase px-2 py-1 rounded bg-black/35 backdrop-blur-sm"
                            style="color: oklch(0.95 0.02 80 / .85);">
                            Now Playing
                        </div>
                    </div>

                    <!-- Track body -->
                    <div class="flex-1 min-w-0 p-6 flex flex-col gap-3">
                        <!-- Chips row -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span v-if="nowPlaying?.genre" class="chip">{{ nowPlaying.genre }}</span>
                            <span v-if="nowPlaying?.year"  class="chip">{{ nowPlaying.year }}</span>
                            <span v-if="nowPlaying?.album" class="chip">{{ nowPlaying.album }}</span>
                        </div>

                        <!-- Title -->
                        <template v-if="nowPlaying">
                            <h2 class="text-[28px] font-semibold leading-[1.05] tracking-tight">
                                {{ nowPlaying.title }}
                            </h2>
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm text-zinc-400">
                                <span class="text-zinc-200 font-medium">{{ nowPlaying.artist }}</span>
                                <span v-if="nowPlaying.album" class="text-zinc-500">·</span>
                                <span v-if="nowPlaying.album" class="italic text-zinc-500">{{ nowPlaying.album }}</span>
                                <span v-if="nowPlaying.duration" class="text-zinc-500">·</span>
                                <span v-if="nowPlaying.duration" class="font-mono text-zinc-500 text-[13px]">{{ formatDuration(nowPlaying.duration) }}</span>
                            </div>
                        </template>
                        <p v-else class="text-zinc-600 text-sm">Nothing playing right now.</p>

                        <!-- Waveform (decorative) -->
                        <div class="mt-auto">
                            <div class="flex items-end gap-[2px] h-[48px] py-1">
                                <div
                                    v-for="(h, i) in waveHeights"
                                    :key="i"
                                    class="flex-1 rounded-[1px] bg-zinc-700 opacity-60"
                                    :style="{ height: h + '%' }"
                                />
                            </div>
                            <div class="flex justify-between font-mono text-[11px] text-zinc-600 mt-1">
                                <span>—:——</span>
                                <span class="text-zinc-500">{{ nowPlaying ? formatDuration(nowPlaying.duration) : '—:——' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Station Health -->
                <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[10.5px] tracking-[.14em] uppercase text-zinc-500 font-medium">Station Health</span>
                        <span class="font-mono text-[11px] text-zinc-600">{{ timeAgo(health?.last_activity_at) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div class="bg-zinc-950 border border-zinc-800/80 rounded-md p-3">
                            <div class="font-mono text-[10px] tracking-[.12em] uppercase text-zinc-600 mb-1.5">Liquidsoap</div>
                            <div class="font-mono text-[14px] flex items-center gap-1.5"
                                :class="health?.liquidsoap_connected ? 'text-zinc-100' : 'text-zinc-500'">
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="health?.liquidsoap_connected ? 'bg-emerald-400 shadow-[0_0_6px_theme(colors.emerald.400)]' : 'bg-zinc-600'"
                                />
                                {{ health?.liquidsoap_connected ? 'Connected' : 'Offline' }}
                            </div>
                        </div>
                        <div class="bg-zinc-950 border border-zinc-800/80 rounded-md p-3">
                            <div class="font-mono text-[10px] tracking-[.12em] uppercase text-zinc-600 mb-1.5">Queue Depth</div>
                            <div class="font-mono text-[14px] text-zinc-100">
                                {{ health?.queue_depth ?? '—' }} <span class="text-zinc-600 text-[11px]">tracks</span>
                            </div>
                        </div>
                        <div class="bg-zinc-950 border border-zinc-800/80 rounded-md p-3 col-span-2">
                            <div class="font-mono text-[10px] tracking-[.12em] uppercase text-zinc-600 mb-1.5">Last Activity</div>
                            <div class="font-mono text-[14px] text-zinc-100">{{ timeAgo(health?.last_activity_at) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Row 2: Up Next ─────────────────────────────────────────── -->
            <div class="grid gap-4" style="grid-template-columns: 1.6fr 1fr;">

                <!-- Up Next -->
                <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-4">
                        <span class="font-mono text-[10.5px] tracking-[.14em] uppercase text-zinc-400 font-medium">Up Next</span>
                        <span class="font-mono text-[11px] text-zinc-600">
                            {{ queue?.length ?? 0 }} tracks
                        </span>
                    </div>

                    <div v-if="queue?.length" class="space-y-0">
                        <div
                            v-for="(item, i) in queue"
                            :key="item.id"
                            class="grid gap-3 items-center py-2 border-t border-zinc-800/60 first:border-transparent"
                            style="grid-template-columns: 20px 44px 1fr;"
                        >
                            <!-- Number -->
                            <span class="font-mono text-[11px] text-zinc-600 text-right">{{ String(i + 1).padStart(2, '0') }}</span>

                            <!-- Art -->
                            <div
                                class="w-11 h-11 rounded flex items-center justify-center text-[18px] font-bold italic select-none"
                                :style="artStyle(i)"
                                style="color: oklch(0.85 0.05 75); font-family: Georgia, serif; text-shadow: 0 1px 2px rgba(0,0,0,.3);"
                            >
                                {{ item.song?.title?.[0]?.toUpperCase() ?? '♪' }}
                            </div>

                            <!-- Info -->
                            <div class="min-w-0">
                                <div class="text-[14px] text-zinc-100 font-medium leading-snug truncate">
                                    {{ item.song?.title ?? '—' }}
                                </div>
                                <div v-if="item.song?.artist" class="text-[12px] text-zinc-500 truncate mt-0.5">
                                    {{ item.song.artist }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-zinc-600">Queue is empty.</p>
                </div>

                <!-- Top Artists -->
                <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-4">
                        <span class="font-mono text-[10.5px] tracking-[.14em] uppercase text-zinc-400 font-medium">Top Artists</span>
                        <span class="font-mono text-[11px] text-zinc-600">last 30d</span>
                    </div>
                    <div v-if="stats?.top_artists?.length" class="space-y-3">
                        <div v-for="a in stats.top_artists" :key="a.artist">
                            <div class="flex items-baseline justify-between mb-1">
                                <span class="text-[13px] text-zinc-300 truncate mr-2">{{ a.artist }}</span>
                                <span class="font-mono text-[12px] text-zinc-500 shrink-0">{{ a.count.toLocaleString() }}</span>
                            </div>
                            <div class="h-[10px] bg-zinc-950 border border-zinc-800 rounded-[2px] overflow-hidden relative">
                                <div
                                    class="absolute inset-y-0 left-0 rounded-[2px]"
                                    :style="{
                                        width: `${(a.count / stats.top_artists[0].count) * 100}%`,
                                        background: 'linear-gradient(to right, #8a6c10 0 70%, #b8941a 70% 90%, #dc2626 90% 100%)'
                                    }"
                                />
                                <div class="absolute inset-0 mix-blend-multiply"
                                    style="background-image: repeating-linear-gradient(to right, transparent 0 9.5%, rgba(0,0,0,.25) 9.5% 10%);" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-zinc-600">No data yet.</p>
                </div>
            </div>

            <!-- ── Row 3: Stats ───────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Play stats -->
                <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-4">
                        <span class="font-mono text-[10.5px] tracking-[.14em] uppercase text-zinc-400 font-medium">Plays</span>
                        <span v-if="stats" class="font-mono text-[11px] text-zinc-600">
                            all time · {{ stats.all_time.toLocaleString() }}
                        </span>
                    </div>
                    <div class="space-y-0">
                        <div class="flex items-baseline justify-between py-2.5 border-t border-dashed border-zinc-800/60 first:border-transparent">
                            <span class="text-[13px] text-zinc-400">Today</span>
                            <span class="text-[26px] font-medium text-zinc-100 leading-none font-mono tracking-tight">{{ stats?.today?.toLocaleString() ?? 0 }}</span>
                        </div>
                        <div class="flex items-baseline justify-between py-2.5 border-t border-zinc-800/60">
                            <span class="text-[13px] text-zinc-400">This week</span>
                            <span class="text-[26px] font-medium text-zinc-100 leading-none font-mono tracking-tight">{{ stats?.week?.toLocaleString() ?? 0 }}</span>
                        </div>
                        <div class="flex items-baseline justify-between py-2.5 border-t border-zinc-800/60">
                            <span class="text-[13px] text-zinc-400">This month</span>
                            <span class="text-[26px] font-medium text-zinc-100 leading-none font-mono tracking-tight">{{ stats?.month?.toLocaleString() ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Top Genres -->
                <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-4">
                        <span class="font-mono text-[10.5px] tracking-[.14em] uppercase text-zinc-400 font-medium">Top Genres</span>
                        <span class="font-mono text-[11px] text-zinc-600">last 30d</span>
                    </div>
                    <div v-if="stats?.top_genres?.length" class="space-y-3">
                        <div v-for="g in stats.top_genres" :key="g.genre">
                            <div class="flex items-baseline justify-between mb-1">
                                <span class="text-[13px] text-zinc-300 truncate mr-2">{{ g.genre }}</span>
                                <span class="font-mono text-[12px] text-zinc-500 shrink-0">{{ g.count.toLocaleString() }}</span>
                            </div>
                            <div class="h-[10px] bg-zinc-950 border border-zinc-800 rounded-[2px] overflow-hidden relative">
                                <div
                                    class="absolute inset-y-0 left-0 rounded-[2px]"
                                    :style="{
                                        width: `${(g.count / stats.top_genres[0].count) * 100}%`,
                                        background: 'linear-gradient(to right, #8a6c10 0 70%, #b8941a 70% 90%, #dc2626 90% 100%)'
                                    }"
                                />
                                <div class="absolute inset-0 mix-blend-multiply"
                                    style="background-image: repeating-linear-gradient(to right, transparent 0 9.5%, rgba(0,0,0,.25) 9.5% 10%);" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-zinc-600">No data yet.</p>
                </div>

            </div>
        </div>

        <RequestSongModal :show="requestModalOpen" @close="requestModalOpen = false" />
    </AdminLayout>
</template>

<style scoped>
.chip {
    display: inline-block;
    font-family: ui-monospace, monospace;
    font-size: 10.5px;
    letter-spacing: .08em;
    padding: 2px 8px;
    border: 1px solid theme('colors.zinc.700');
    border-radius: 4px;
    color: theme('colors.zinc.400');
    background: theme('colors.zinc.950');
}
</style>
