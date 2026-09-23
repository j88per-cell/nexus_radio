<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const searchQuery = ref('');

function doSearch() {
    if (searchQuery.value.trim()) {
        router.get('/encyclopedia', { q: searchQuery.value.trim() });
    }
}
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100">

        <!-- Top bar -->
        <header class="sticky top-0 z-30 bg-zinc-900/95 backdrop-blur border-b border-zinc-800">
            <div class="max-w-6xl mx-auto px-6 h-14 flex items-center gap-6">
                <Link href="/" class="shrink-0">
                    <span class="text-xs tracking-[0.25em] uppercase text-zinc-500">Nexus Radio</span>
                    <span class="ml-2 text-sm font-bold text-red-500">Radio</span>
                </Link>

                <span class="text-zinc-700">|</span>

                <Link href="/encyclopedia" class="text-sm font-semibold text-zinc-200 hover:text-white transition-colors tracking-wide">
                    Encyclopedia
                </Link>

                <!-- Search -->
                <form @submit.prevent="doSearch" class="flex-1 max-w-sm ml-auto">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search bands, people…"
                            class="w-full bg-zinc-800 border border-zinc-700 rounded text-sm text-zinc-100 placeholder-zinc-500 px-3 py-1.5 pr-8 focus:outline-none focus:border-zinc-500 transition-colors"
                        />
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-white">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/>
                            </svg>
                        </button>
                    </div>
                </form>

                <Link href="/admin/dashboard" class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors shrink-0">
                    Admin
                </Link>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 py-8">
            <slot />
        </main>
    </div>
</template>
