<script setup lang="ts">
import { computed } from 'vue';
import { buildPreviewLayout } from '@/lib/templatePreview';
import type { NodeTypeCatalog, TemplatePreviewGraph } from '@/types';

/*
 * Aperçu SVG statique du graphe d'un template (maquette templates.html,
 * D10) : rendu purement déclaratif du layout calculé par lib/templatePreview.
 * Non interactif — aucune poignée, aucun écouteur ; lisible clair/sombre via
 * les tokens. Les libellés sont rendus par interpolation Vue échappée dans
 * des <text> SVG : le contenu du snapshot n'est jamais interprété comme HTML.
 */
const props = defineProps<{
    graph: TemplatePreviewGraph;
    nodeTypes: NodeTypeCatalog;
}>();

const layout = computed(() => buildPreviewLayout(props.graph, props.nodeTypes));

const ariaLabel = computed(
    () => `Aperçu du graphe — ${layout.value.nodes.length} nodes`,
);
</script>

<template>
    <svg
        :viewBox="layout.viewBox"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        :aria-label="ariaLabel"
        class="preview-svg"
    >
        <path
            v-for="(path, index) in layout.edgePaths"
            :key="`edge-${index}`"
            :d="path"
            class="preview-edge"
        />

        <g v-for="node in layout.nodes" :key="node.key">
            <rect
                :x="node.x"
                :y="node.y"
                width="70"
                height="22"
                rx="7"
                class="preview-node"
            />
            <rect
                :x="node.x"
                :y="node.y"
                width="70"
                height="22"
                rx="7"
                :fill="node.colorToken"
                opacity="0.13"
            />
            <circle
                :cx="node.x + 10"
                :cy="node.y + 11"
                r="3"
                :fill="node.colorToken"
            />
            <text :x="node.x + 19" :y="node.y + 13.8" class="preview-label">
                {{ node.label }}
            </text>
        </g>
    </svg>
</template>

<style scoped>
/* Styles portés de la maquette (.tpl-preview svg, .tpl-node, .tpl-edge). */
.preview-svg {
    display: block;
    width: 100%;
    height: 100%;
}

.preview-node {
    fill: var(--card);
    stroke: var(--border);
    stroke-width: 1.2;
}

.preview-edge {
    fill: none;
    stroke: var(--muted-foreground);
    stroke-width: 1.4;
    opacity: 0.55;
}

.preview-label {
    font-size: 7.5px;
    font-weight: 500;
    fill: var(--foreground);
    opacity: 0.72;
}
</style>
