<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);

onMounted(() => {
    const saved = localStorage.getItem('nexus-radio-sidebar-open');
    sidebarOpen.value = saved === 'true';
});

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
    localStorage.setItem('nexus-radio-sidebar-open', String(sidebarOpen.value));
}

function userInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

const nav = [
    { group: 'Booth', items: [
        { label: 'Dashboard', href: '/admin/dashboard', name: 'admin.dashboard', ico: 'dashboard' },
        { label: 'Queue',     href: '/admin/queue',     name: 'admin.queue',     ico: 'queue' },
        { label: 'Shows',    href: '/admin/shows',    name: 'admin.shows',    ico: 'shows' },
    ]},
    { group: 'Library', items: [
        { label: 'Artists',      href: '/admin/artists',         name: 'admin.artists',         ico: 'artists' },
        { label: 'Band Members', href: '/admin/band-members',    name: 'admin.band-members',    ico: 'members' },
        { label: 'People',       href: '/admin/people',          name: 'admin.people',          ico: 'people' },
        { label: 'Songs',        href: '/admin/songs',           name: 'admin.songs',           ico: 'songs' },
        { label: 'Releases',     href: '/admin/releases',        name: 'admin.releases',        ico: 'releases' },
        { label: 'Rel. Credits', href: '/admin/release-credits', name: 'admin.release-credits', ico: 'credits' },
    ]},
    { group: 'Admin', items: [
        { label: 'Connections', href: '/admin/connections', name: 'admin.connections', ico: 'connect' },
        { label: 'Settings',   href: '/admin/settings',   name: 'admin.settings',   ico: 'settings' },
    ]},
];

const ICONS = {
    dashboard: '<rect x="2" y="2" width="6" height="9" rx="1"/><rect x="10" y="2" width="6" height="5" rx="1"/><rect x="10" y="9" width="6" height="7" rx="1"/><rect x="2" y="13" width="6" height="3" rx="1"/>',
    queue:     '<line x1="3" y1="5" x2="15" y2="5"/><line x1="3" y1="9" x2="15" y2="9"/><line x1="3" y1="13" x2="11" y2="13"/>',
    shows:     '<rect x="2.5" y="3.5" width="13" height="11" rx="1.2"/><line x1="2.5" y1="7" x2="15.5" y2="7"/><circle cx="6" cy="5.2" r=".5" fill="currentColor"/><circle cx="8" cy="5.2" r=".5" fill="currentColor"/>',
    artists:   '<circle cx="6.5" cy="6.5" r="2.5"/><path d="M2 14c.7-2.2 2.5-3.5 4.5-3.5S10.3 11.8 11 14"/><circle cx="12.5" cy="5" r="2"/><path d="M10 9.5c.5-.4 1.4-.7 2.5-.7 2 0 3 1.2 3.5 2.7"/>',
    members:   '<circle cx="9" cy="6" r="2.5"/><path d="M3 15c.5-2.6 2.7-4 6-4s5.5 1.4 6 4"/>',
    people:    '<circle cx="9" cy="6.5" r="3"/><path d="M3 15.5c.7-2.7 3-4.5 6-4.5s5.3 1.8 6 4.5"/>',
    songs:     '<path d="M6 4l8-1.5v9.5"/><circle cx="5" cy="13" r="2"/><circle cx="13" cy="11.5" r="2"/>',
    releases:  '<circle cx="9" cy="9" r="6"/><circle cx="9" cy="9" r="2.5"/><circle cx="9" cy="9" r=".8" fill="currentColor"/>',
    credits:   '<rect x="2.5" y="4" width="13" height="10" rx="1"/><line x1="2.5" y1="7.5" x2="15.5" y2="7.5"/><line x1="5" y1="10.5" x2="9" y2="10.5"/>',
    encyclo:   '<path d="M3 4v10a1 1 0 0 0 1 1h10V4H4a1 1 0 0 0-1 1z"/><line x1="6" y1="4" x2="6" y2="14"/>',
    broadcast: '<path d="M9 9v5"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/><path d="M5.5 9a3.5 3.5 0 0 1 7 0"/><path d="M3 9a6 6 0 0 1 12 0"/>',
    connect:   '<circle cx="5" cy="9" r="2"/><circle cx="13" cy="5" r="2"/><circle cx="13" cy="13" r="2"/><line x1="6.5" y1="8" x2="11.5" y2="5.7"/><line x1="6.5" y1="10" x2="11.5" y2="12.3"/>',
    settings:  '<circle cx="9" cy="9" r="2"/><path d="M9 2v2M9 14v2M2 9h2M14 9h2M3.8 3.8l1.4 1.4M12.8 12.8l1.4 1.4M3.8 14.2l1.4-1.4M12.8 5.2l1.4-1.4"/>',
};

function isActive(name) {
    return route().current(name);
}

const flashStatus  = computed(() => page.props.flash?.status);
const flashWarning = computed(() => page.props.flash?.warning);
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100 flex">

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex flex-col bg-zinc-900 border-r border-zinc-800 overflow-hidden"
            :class="sidebarOpen ? 'w-[232px]' : 'w-16'"
            style="transition: width 260ms cubic-bezier(.2,.7,.2,1);"
        >
            <!-- Brand -->
            <div class="flex items-center gap-2.5 px-3.5 py-4 border-b border-zinc-800 shrink-0 overflow-hidden">
                <div class="w-8 h-8 rounded-md shrink-0 flex items-center justify-center bg-gradient-to-br from-brass-light to-brass font-serif font-bold italic text-zinc-950 text-lg select-none shadow-inner">
                    M
                </div>
                <div
                    class="overflow-hidden whitespace-nowrap"
                    :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'"
                    style="transition: opacity 200ms, width 260ms cubic-bezier(.2,.7,.2,1);"
                >
                    <div class="text-[17px] font-semibold leading-none tracking-[.01em]">Nexus<em class="italic text-brass">Radio</em></div>
                    <div class="font-mono text-[9.5px] tracking-[.22em] uppercase text-zinc-500 mt-0.5">Web Broadcasting</div>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-2.5 px-2">
                <div v-for="group in nav" :key="group.group" class="mt-3.5 first:mt-1">
                    <!-- Group label -->
                    <div
                        class="px-2.5 py-1 font-mono text-[9.5px] tracking-[.18em] uppercase text-zinc-600 overflow-hidden whitespace-nowrap"
                        :class="sidebarOpen ? 'opacity-100' : 'opacity-0'"
                        style="transition: opacity 200ms;"
                    >{{ group.group }}</div>

                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="relative flex items-center gap-3 px-2.5 py-2 my-0.5 rounded-md text-[13.5px] leading-none whitespace-nowrap transition-colors"
                        :class="isActive(item.name)
                            ? 'bg-brass/10 text-zinc-100'
                            : 'text-zinc-400 hover:bg-zinc-800/50 hover:text-zinc-200'"
                        :title="!sidebarOpen ? item.label : undefined"
                    >
                        <span
                            v-if="isActive(item.name)"
                            class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-r bg-brass"
                        />
                        <svg
                            class="w-[18px] h-[18px] shrink-0 transition-colors"
                            :class="isActive(item.name) ? 'text-brass' : 'text-zinc-500'"
                            viewBox="0 0 18 18" fill="none" stroke="currentColor"
                            stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"
                            v-html="ICONS[item.ico]"
                        />
                        <span
                            class="overflow-hidden"
                            :class="sidebarOpen ? 'opacity-100 max-w-[180px]' : 'opacity-0 max-w-0'"
                            style="transition: opacity 200ms, max-width 260ms cubic-bezier(.2,.7,.2,1);"
                        >{{ item.label }}</span>
                    </Link>
                </div>

                <!-- Encyclopedia -->
                <div class="mt-5 border-t border-zinc-800/60 pt-3">
                    <Link
                        href="/encyclopedia"
                        class="flex items-center gap-3 px-2.5 py-2 rounded-md text-zinc-600 hover:text-zinc-400 transition-colors text-[13px]"
                        :title="!sidebarOpen ? 'Encyclopedia' : undefined"
                    >
                        <svg
                            class="w-[18px] h-[18px] shrink-0"
                            viewBox="0 0 18 18" fill="none" stroke="currentColor"
                            stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"
                            v-html="ICONS.encyclo"
                        />
                        <span
                            class="overflow-hidden"
                            :class="sidebarOpen ? 'opacity-100 max-w-[180px]' : 'opacity-0 max-w-0'"
                            style="transition: opacity 200ms, max-width 260ms cubic-bezier(.2,.7,.2,1);"
                        >Encyclopedia</span>
                    </Link>
                </div>
            </nav>

            <!-- Footer: user + collapse btn -->
            <div
                class="border-t border-zinc-800 p-3 shrink-0 flex items-center"
                :class="sidebarOpen ? 'gap-2.5' : 'justify-center'"
            >
                <template v-if="sidebarOpen">
                    <div class="w-[30px] h-[30px] rounded-full shrink-0 flex items-center justify-center bg-gradient-to-br from-brass to-brass-dark text-zinc-950 font-semibold text-[13px] select-none">
                        {{ userInitials(user?.name) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[12.5px] text-zinc-300 font-medium truncate">{{ user?.name ?? user?.email }}</div>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="text-[11px] text-zinc-500 hover:text-red-400 transition-colors"
                        >Log out</Link>
                    </div>
                </template>
                <button
                    @click="toggleSidebar"
                    class="w-6 h-6 rounded border border-zinc-700 text-zinc-500 hover:text-zinc-300 hover:border-zinc-600 flex items-center justify-center shrink-0"
                    :class="sidebarOpen ? 'ml-auto' : 'rotate-180'"
                    style="transition: transform 260ms cubic-bezier(.2,.7,.2,1);"
                >
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M7.5 2L3.5 6l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Main area -->
        <div
            class="flex-1 min-h-screen flex flex-col"
            :class="sidebarOpen ? 'ml-[232px]' : 'ml-16'"
            style="transition: margin-left 260ms cubic-bezier(.2,.7,.2,1);"
        >
            <header v-if="$slots.header" class="border-b border-zinc-800 px-8 py-5 bg-zinc-900/50">
                <slot name="header" />
            </header>
            <main class="flex-1 px-6 py-5">
                <slot />
            </main>
        </div>

        <!-- Flash toast -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="flashStatus || flashWarning"
                :key="flashStatus || flashWarning"
                class="fixed bottom-5 right-5 z-50 max-w-sm px-4 py-3 rounded-md border text-[13px] shadow-lg"
                :class="flashWarning
                    ? 'bg-amber-950/90 border-amber-800 text-amber-300'
                    : 'bg-zinc-900 border-zinc-700 text-zinc-200'"
            >{{ flashWarning || flashStatus }}</div>
        </Transition>
    </div>
</template>
