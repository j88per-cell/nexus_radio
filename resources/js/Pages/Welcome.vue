<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    streamUrl: { type: String, required: true },
    auth: { type: Object, default: () => ({}) },
});

const current  = ref(null);
const history  = ref([]);
const upcoming = ref(null);
const isPlaying = ref(false);
const loading   = ref(false);
const audio     = ref(null);
let   pollTimer = null;

async function fetchNowPlaying() {
    try {
        const res  = await fetch('/api/now-playing');
        const data = await res.json();
        current.value  = data.current  ?? null;
        history.value  = data.history  ?? [];
        upcoming.value = data.upcoming ?? null;
    } catch {
        // silently ignore — station may be warming up
    }
}

function togglePlay() {
    if (!audio.value) return;
    if (isPlaying.value) {
        audio.value.pause();
    } else {
        loading.value = true;
        audio.value.play();
    }
}

onMounted(() => {
    fetchNowPlaying();
    pollTimer = setInterval(fetchNowPlaying, 15000);
});

onUnmounted(() => {
    clearInterval(pollTimer);
});
</script>

<template>
    <Head title="Nexus Radio" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col">

        <!-- Hidden audio element -->
        <audio
            ref="audio"
            :src="streamUrl"
            preload="none"
            @playing="isPlaying = true; loading = false"
            @pause="isPlaying = false"
            @waiting="loading = true"
            @canplay="loading = false"
        />

        <!-- Nav -->
        <nav class="flex justify-end px-6 py-4">
            <Link
                v-if="auth.user"
                :href="route('dashboard')"
                class="text-sm text-zinc-500 hover:text-zinc-300 transition-colors"
            >
                Dashboard
            </Link>
            <Link
                v-else
                :href="route('login')"
                class="text-sm text-zinc-500 hover:text-zinc-300 transition-colors"
            >
                Admin
            </Link>
        </nav>

        <!-- Main -->
        <main class="flex-1 flex flex-col items-center px-6 pb-16 pt-8 gap-10">

            <!-- Station identity -->
            <div class="text-center space-y-3">
                <p class="text-sm tracking-[0.3em] uppercase text-zinc-500">Broadcasting from the void</p>
                <h1 class="text-7xl font-bold tracking-tight text-white">Nexus</h1>
                <p class="text-4xl font-light tracking-[0.2em] text-red-500 uppercase">Radio</p>
            </div>

            <!-- Body -->
            <div class="w-full max-w-xl">

                <!-- Player + playlist -->
                <div class="flex flex-col items-center gap-8">

                    <!-- Play button -->
                    <button
                        @click="togglePlay"
                        class="w-28 h-28 rounded-full border-2 border-zinc-600 hover:border-red-500 transition-all duration-300 flex items-center justify-center group"
                        :class="isPlaying ? 'border-red-500 bg-red-500/10' : 'hover:bg-zinc-900'"
                        :aria-label="isPlaying ? 'Pause' : 'Play'"
                    >
                        <svg v-if="loading" class="w-10 h-10 text-zinc-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <svg v-else-if="isPlaying" class="w-10 h-10 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                        </svg>
                        <svg v-else class="w-10 h-10 text-zinc-400 group-hover:text-white transition-colors translate-x-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7L8 5z"/>
                        </svg>
                    </button>

                    <!-- Playlist -->
                    <div class="w-full space-y-1">

                        <!-- History -->
                        <div
                            v-for="(song, i) in history.slice().reverse()"
                            :key="'h' + i"
                            class="flex items-baseline gap-3 px-4 py-2 rounded"
                        >
                            <span class="text-xs tracking-widest uppercase w-20 shrink-0 text-right text-zinc-700">Played</span>
                            <span class="truncate text-zinc-600">{{ song.title }}</span>
                            <span class="text-zinc-700 truncate text-sm">{{ song.artist }}</span>
                        </div>

                        <!-- Current -->
                        <div class="w-full text-center py-6 min-h-[8rem]">
                            <template v-if="current">
                                <p class="text-xs tracking-widest uppercase text-zinc-500 mb-3">Now Playing</p>
                                <p class="text-white font-semibold text-2xl leading-tight">{{ current.title }}</p>
                                <p class="text-zinc-400 text-xl mt-2">{{ current.artist }}</p>
                                <p v-if="current.album" class="text-zinc-600 text-base mt-1">
                                    {{ current.album }}<span v-if="current.year"> · {{ current.year }}</span>
                                </p>
                            </template>
                            <template v-else>
                                <p class="text-zinc-600 text-lg">Warming up the void…</p>
                            </template>
                        </div>

                        <!-- Up next -->
                        <div v-if="upcoming" class="flex items-baseline gap-3 px-4 py-2 rounded">
                            <span class="text-xs tracking-widest uppercase w-20 shrink-0 text-right text-zinc-700">Up Next</span>
                            <span class="truncate text-zinc-600">{{ upcoming.title }}</span>
                            <span class="text-zinc-700 truncate text-sm">{{ upcoming.artist }}</span>
                        </div>

                    </div>
                </div>

            </div>
        </main>

        <!-- Footer -->
        <footer class="text-center py-4 text-zinc-700 text-sm">
            Nexus Radio &mdash; All signal, no static.
        </footer>

    </div>
</template>
