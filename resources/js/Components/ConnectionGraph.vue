<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import cytoscape from 'cytoscape';

const props = defineProps({
    nodes: { type: Array, default: () => [] },
    edges: { type: Array, default: () => [] },
    hero:  { type: Boolean, default: false },
});

const container = ref(null);
let cy = null;

const nodeColors = {
    center:    { bg: '#ef4444', border: '#f87171', text: '#fff' },  // red — the artist
    member:    { bg: '#3b82f6', border: '#60a5fa', text: '#fff' },  // blue — current member
    alumni:    { bg: '#1d4ed8', border: '#3b82f6', text: '#dbeafe' }, // dark blue — former
    genre:     { bg: '#16a34a', border: '#4ade80', text: '#dcfce7' }, // green — genre
    influence: { bg: '#9333ea', border: '#c084fc', text: '#f3e8ff' }, // purple — influence
    connected: { bg: '#d97706', border: '#fbbf24', text: '#fef3c7' }, // amber — connected band
    person:    { bg: '#0891b2', border: '#22d3ee', text: '#cffafe' }, // cyan — person (generic)
};

function buildStyles() {
    const styles = [
        {
            selector: 'node',
            style: {
                'label': 'data(label)',
                'font-size': '11px',
                'font-family': 'ui-sans-serif, system-ui, sans-serif',
                'text-valign': 'center',
                'text-halign': 'center',
                'text-wrap': 'wrap',
                'text-max-width': '80px',
                'width': '60px',
                'height': '60px',
                'cursor': 'pointer',
            },
        },
        {
            selector: 'node[type="center"]',
            style: {
                'width': '80px',
                'height': '80px',
                'background-color': nodeColors.center.bg,
                'border-color': nodeColors.center.border,
                'border-width': 3,
                'color': nodeColors.center.text,
                'font-size': '12px',
                'font-weight': 'bold',
            },
        },
        {
            selector: 'edge',
            style: {
                'label': 'data(label)',
                'font-size': '9px',
                'font-family': 'ui-sans-serif, system-ui, sans-serif',
                'color': '#71717a',
                'text-rotation': 'autorotate',
                'text-margin-y': '-6px',
                'curve-style': 'bezier',
                'line-color': '#3f3f46',
                'target-arrow-color': '#3f3f46',
                'target-arrow-shape': 'triangle',
                'arrow-scale': 0.8,
                'width': 1.5,
            },
        },
        {
            selector: 'node:selected',
            style: {
                'border-width': 3,
                'border-color': '#f87171',
            },
        },
    ];

    // Color each node type
    Object.entries(nodeColors).forEach(([type, colors]) => {
        if (type === 'center') return;
        styles.push({
            selector: `node[type="${type}"]`,
            style: {
                'background-color': colors.bg,
                'border-color': colors.border,
                'border-width': 2,
                'color': colors.text,
            },
        });
    });

    return styles;
}

function init() {
    if (!container.value) return;

    cy?.destroy();

    cy = cytoscape({
        container: container.value,
        elements: {
            nodes: props.nodes,
            edges: props.edges,
        },
        style: buildStyles(),
        layout: {
            name: 'cose',
            animate: false,
            nodeRepulsion: () => 6000,
            idealEdgeLength: () => 100,
            nodeDimensionsIncludeLabels: true,
            padding: 30,
        },
        userZoomingEnabled: true,
        userPanningEnabled: true,
        boxSelectionEnabled: false,
        minZoom: 0.3,
        maxZoom: 3,
    });

    cy.on('tap', 'node', (e) => {
        const href = e.target.data('href');
        if (href && href !== '') {
            router.visit(href);
        }
    });

    // Pointer cursor on hover
    cy.on('mouseover', 'node', () => {
        container.value.style.cursor = 'pointer';
    });
    cy.on('mouseout', 'node', () => {
        container.value.style.cursor = 'default';
    });
}

onMounted(init);

watch(() => [props.nodes, props.edges], init, { deep: true });

onUnmounted(() => {
    cy?.destroy();
});
</script>

<template>
    <div>
        <!-- Legend -->
        <div
            class="flex flex-wrap gap-3 text-xs text-zinc-400"
            :class="hero ? 'px-8 mb-2' : 'mb-3'"
        >
            <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span> Artist</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-500"></span> Current Member</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-800"></span> Former Member</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-green-600"></span> Genre</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-purple-600"></span> Influence</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-600"></span> Connected Band</span>
        </div>

        <div
            ref="container"
            class="w-full bg-zinc-900 border-zinc-800"
            :class="hero
                ? 'h-[480px] border-y'
                : 'h-[420px] rounded-lg border'"
        />

        <p
            class="text-xs text-zinc-600 mt-2 text-center"
            :class="hero ? 'px-8' : ''"
        >Click any node to navigate · Drag to pan · Scroll to zoom</p>
    </div>
</template>
