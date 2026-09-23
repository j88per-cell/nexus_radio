<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    stream_url:        props.settings?.stream_url        ?? '',
    station_name:      props.settings?.station_name      ?? '',
    dj_name:           props.settings?.dj_name           ?? '',
    dj_drop_frequency: props.settings?.dj_drop_frequency ?? 3,
});

function save() {
    form.patch(route('admin.settings.update'));
}
</script>

<template>
    <Head title="Settings" />
    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-zinc-100">Settings</h1>
        </template>

        <form @submit.prevent="save" class="space-y-6 max-w-2xl">

            <!-- Station -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-5 space-y-4">
                <p class="text-sm tracking-widest uppercase text-zinc-400 font-medium mb-2">Station</p>

                <div>
                    <label class="block text-base text-zinc-300 mb-1">Station Name</label>
                    <input
                        v-model="form.station_name"
                        type="text"
                        class="w-full bg-zinc-800 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                        placeholder="Nexus Radio"
                    />
                </div>

                <div>
                    <label class="block text-base text-zinc-300 mb-1">Stream URL</label>
                    <input
                        v-model="form.stream_url"
                        type="text"
                        class="w-full bg-zinc-800 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                        placeholder="http://localhost:8000/stream"
                    />
                </div>
            </div>

            <!-- DJ -->
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-5 space-y-4">
                <p class="text-sm tracking-widest uppercase text-zinc-400 font-medium mb-2">DJ</p>

                <div>
                    <label class="block text-base text-zinc-300 mb-1">DJ Name</label>
                    <input
                        v-model="form.dj_name"
                        type="text"
                        class="w-full bg-zinc-800 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-zinc-400"
                        placeholder="Penny Poison"
                    />
                </div>

                <div>
                    <label class="block text-base text-zinc-300 mb-1">Drop Frequency</label>
                    <p class="text-sm text-zinc-400 mb-1.5">Play a DJ drop every N songs.</p>
                    <input
                        v-model.number="form.dj_drop_frequency"
                        type="number"
                        min="1"
                        max="20"
                        class="w-24 bg-zinc-800 border border-zinc-600 rounded px-3 py-2 text-base text-zinc-100 focus:outline-none focus:border-zinc-400"
                    />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-base rounded transition-colors"
                >
                    Save Changes
                </button>
                <span v-if="form.recentlySuccessful" class="text-base text-green-400">Saved.</span>
            </div>
        </form>
    </AdminLayout>
</template>
