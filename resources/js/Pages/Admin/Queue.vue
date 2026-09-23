<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    queue:    Array,
    liveShow: Object,
});

function formatDuration(secs) {
    if (!secs) return '—';
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

const statusClass = {
    pending: 'text-zinc-400',
    pushed:  'text-amber-400',
    playing: 'text-green-400',
};

function resetQueue() {
    if (confirm('Clear the queue, end any live manual show, and restart free play?')) {
        router.post(route('admin.queue.reset'));
    }
}
</script>

<template>
    <Head title="Queue" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold text-zinc-100">Queue</h1>
                <button
                    @click="resetQueue"
                    class="px-3 py-1.5 text-sm bg-red-600 hover:bg-red-500 text-white rounded transition"
                >
                    Reset Queue
                </button>
            </div>
        </template>

        <!-- Live show banner -->
        <div v-if="liveShow" class="mb-4 px-4 py-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm">
            Live show: <strong>{{ liveShow.name }}</strong> ({{ liveShow.mode }}) — Reset Queue will end it and restart free play.
        </div>

        <div class="bg-zinc-900 border border-zinc-700 rounded-lg">
            <div class="px-5 py-4 border-b border-zinc-700 flex items-center justify-between">
                <p class="text-sm tracking-widest uppercase text-zinc-400 font-medium">Active Queue</p>
                <span class="text-sm text-zinc-400">{{ queue?.length ?? 0 }} items</span>
            </div>

            <div v-if="queue?.length" class="divide-y divide-zinc-800">
                <div
                    v-for="(item, i) in queue"
                    :key="item.id"
                    class="flex items-center gap-4 px-5 py-3"
                >
                    <span class="text-sm text-zinc-500 w-5 text-right shrink-0">{{ i + 1 }}</span>

                    <span v-if="item.type === 'dj'" class="w-7 h-7 rounded bg-red-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="9" y="2" width="6" height="11" rx="3"/>
                            <path stroke-linecap="round" d="M19 10a7 7 0 0 1-14 0M12 19v3M8 22h8"/>
                        </svg>
                    </span>
                    <span v-else class="w-7 h-7 rounded bg-zinc-700 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-zinc-300" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 19V6l12-3v13M9 19a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm12 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                        </svg>
                    </span>

                    <div class="flex-1 min-w-0">
                        <p class="text-base text-zinc-100 truncate">{{ item.song?.title ?? 'DJ Segment' }}</p>
                        <p v-if="item.song?.artist" class="text-sm text-zinc-400 truncate">{{ item.song.artist }}</p>
                        <p v-if="!item.audio_path" class="text-xs text-red-400">no audio path</p>
                    </div>

                    <div class="flex items-center gap-4 shrink-0">
                        <span :class="statusClass[item.status] ?? 'text-zinc-400'" class="text-xs uppercase tracking-wide">{{ item.status }}</span>
                        <span v-if="item.song?.duration" class="text-sm text-zinc-400">{{ formatDuration(item.song.duration) }}</span>
                    </div>
                </div>
            </div>

            <div v-else class="px-5 py-12 text-center">
                <p class="text-zinc-400 text-base">Queue is empty.</p>
            </div>
        </div>
    </AdminLayout>
</template>
