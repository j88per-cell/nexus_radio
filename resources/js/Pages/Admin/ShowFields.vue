<script setup>
const props = defineProps({
    form: Object,
});

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function toggleDay(day) {
    const arr = props.form.recurrence_days;
    const idx = arr.indexOf(day);
    if (idx === -1) arr.push(day);
    else arr.splice(idx, 1);
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <!-- Name -->
            <div>
                <label class="block text-xs text-zinc-400 mb-1">Name</label>
                <input v-model="form.name" type="text" required
                    class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500" />
                <p v-if="form.errors?.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <!-- Mode -->
            <div>
                <label class="block text-xs text-zinc-400 mb-1">Mode</label>
                <select v-model="form.mode"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500">
                    <option value="auto">Auto</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="block text-xs text-zinc-400 mb-1">Description</label>
            <textarea v-model="form.description" rows="2"
                class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500 resize-none" />
        </div>

        <!-- Theme -->
        <div>
            <label class="block text-xs text-zinc-400 mb-1">Theme</label>
            <textarea v-model="form.theme" rows="2"
                class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500 resize-none" />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <!-- Status -->
            <div>
                <label class="block text-xs text-zinc-400 mb-1">Status</label>
                <select v-model="form.status"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="done">Done</option>
                </select>
            </div>

            <!-- Priority -->
            <div>
                <label class="block text-xs text-zinc-400 mb-1">Priority</label>
                <input v-model.number="form.priority" type="number" min="0" max="100"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500" />
            </div>

            <!-- Duration override -->
            <div>
                <label class="block text-xs text-zinc-400 mb-1">Duration (min) <span class="text-zinc-600">optional</span></label>
                <input v-model.number="form.duration_minutes" type="number" min="1" placeholder="auto"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white placeholder-zinc-600 focus:outline-none focus:border-zinc-500" />
            </div>
        </div>

        <!-- Recurrence -->
        <div>
            <label class="block text-xs text-zinc-400 mb-1">Recurrence</label>
            <div class="flex gap-4">
                <label v-for="opt in ['once', 'interval', 'daily', 'weekly']" :key="opt"
                    class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" v-model="form.recurrence" :value="opt" class="accent-red-500" />
                    <span class="text-sm text-zinc-300 capitalize">{{ opt }}</span>
                </label>
            </div>
        </div>

        <!-- Once: exact datetime -->
        <div v-if="form.recurrence === 'once'">
            <label class="block text-xs text-zinc-400 mb-1">Scheduled at</label>
            <input v-model="form.scheduled_at" type="datetime-local"
                class="bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500" />
        </div>

        <!-- Interval: every N hours -->
        <div v-if="form.recurrence === 'interval'">
            <label class="block text-xs text-zinc-400 mb-1">Every N hours</label>
            <input v-model.number="form.interval_hours" type="number" min="1"
                class="w-28 bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500" />
        </div>

        <!-- Daily / Weekly: time + optional days -->
        <div v-if="form.recurrence === 'daily' || form.recurrence === 'weekly'" class="flex gap-6 flex-wrap">
            <div>
                <label class="block text-xs text-zinc-400 mb-1">Start time</label>
                <input v-model="form.start_time" type="time"
                    class="bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-zinc-500" />
            </div>
            <div v-if="form.recurrence === 'weekly'">
                <label class="block text-xs text-zinc-400 mb-1">Days</label>
                <div class="flex gap-1.5 flex-wrap">
                    <button v-for="(day, i) in DAYS" :key="i" type="button"
                        @click="toggleDay(i)"
                        :class="['px-2 py-1 text-xs rounded border transition-colors',
                            form.recurrence_days.includes(i)
                                ? 'bg-red-600 border-red-600 text-white'
                                : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:border-zinc-500']">
                        {{ day }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
