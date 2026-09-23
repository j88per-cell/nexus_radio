<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    liveShow: Object,
    harbor:   Object,
    muted:    { type: Boolean, default: null },
    status:   String,
});

const busy = ref(false);

function toggleMute() {
    busy.value = true;
    const action = props.muted ? 'live.unmute' : 'live.mute';
    router.post(route(action), {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

const connected = computed(() => {
    if (!props.status) return null;
    return /no source|not connected|disconnected/i.test(props.status) ? false : true;
});
</script>

<template>
    <Head title="Live" />
    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-zinc-100">Live</h1>
        </template>

        <div class="max-w-2xl space-y-6">

            <!-- Connection info -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-5">
                <h2 class="text-sm font-semibold text-zinc-200 mb-3">Broadcasting client connection</h2>
                <p class="text-xs text-zinc-500 mb-4">
                    Point Mixxx, BUTT, OBS, or any Icecast-source-compatible client at this
                    address to go live. It connects straight to Liquidsoap, not to Icecast.
                </p>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-zinc-500">Server / Host</dt>
                    <dd class="text-zinc-200 font-mono">{{ harbor.host }}</dd>
                    <dt class="text-zinc-500">Port</dt>
                    <dd class="text-zinc-200 font-mono">{{ harbor.port }}</dd>
                    <dt class="text-zinc-500">Mount</dt>
                    <dd class="text-zinc-200 font-mono">/{{ harbor.mount }}</dd>
                    <dt class="text-zinc-500">User</dt>
                    <dd class="text-zinc-200 font-mono">source</dd>
                    <dt class="text-zinc-500">Password</dt>
                    <dd class="text-zinc-200 font-mono">{{ harbor.password }}</dd>
                </dl>
            </div>

            <!-- Status + mute -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-5">
                <h2 class="text-sm font-semibold text-zinc-200 mb-3">On-air control</h2>
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-2.5 h-2.5 rounded-full inline-block"
                        :class="connected === true ? 'bg-green-500' : connected === false ? 'bg-zinc-600' : 'bg-zinc-700'" />
                    <span class="text-sm text-zinc-300">
                        {{ connected === true ? 'Mic connected' : connected === false ? 'No mic connected' : 'Status unknown' }}
                    </span>
                </div>
                <button
                    @click="toggleMute"
                    :disabled="busy || muted === null"
                    class="px-4 py-2 text-sm rounded transition-colors disabled:opacity-50"
                    :class="muted ? 'bg-green-700 hover:bg-green-600 text-white' : 'bg-red-700 hover:bg-red-600 text-white'"
                >
                    {{ muted ? 'Unmute mic' : 'Mute mic' }}
                </button>
                <p class="text-xs text-zinc-500 mt-2">
                    Muting keeps the client connected but stops routing it to the stream —
                    the normal track queue plays instead until you unmute.
                </p>
                <p v-if="status" class="text-xs text-zinc-600 font-mono mt-3 whitespace-pre-wrap">{{ status }}</p>
            </div>

            <!-- Manual show / running order -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-5">
                <h2 class="text-sm font-semibold text-zinc-200 mb-3">Running order</h2>
                <template v-if="liveShow">
                    <p class="text-sm text-zinc-300">
                        "<strong>{{ liveShow.name }}</strong>" is live
                        <span v-if="!liveShow.shuffle">— playing your curated order</span>
                        <span v-else>— shuffling your curated set</span>.
                    </p>
                    <a :href="route('admin.shows')" class="text-xs text-blue-400 hover:text-blue-300 mt-2 inline-block">
                        Manage tracklist in Shows →
                    </a>
                </template>
                <template v-else>
                    <p class="text-sm text-zinc-500">
                        No manual show is live. To program a running order, create a Show in
                        <strong>Manual</strong> mode with shuffle off, build the tracklist, and
                        start it — then connect and go live here.
                    </p>
                    <a :href="route('admin.shows')" class="text-xs text-blue-400 hover:text-blue-300 mt-2 inline-block">
                        Go to Shows →
                    </a>
                </template>
            </div>

        </div>
    </AdminLayout>
</template>
