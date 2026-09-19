<script setup lang="ts">
// Style de base de vue-flow (positionnement uniquement, neutre) —
// volontairement SANS theme-default.css : tout l'habillage vient des tokens.
import '@vue-flow/core/dist/style.css';
import { Background } from '@vue-flow/background';
import { VueFlow, type Connection } from '@vue-flow/core';
import { computed } from 'vue';
import WorkflowNodeCard from '@/components/builder/WorkflowNodeCard.vue';
import type {
    BuilderConnection,
    BuilderFlowEdge,
    BuilderFlowNode,
} from '@/composables/useWorkflowBuilder';

const props = defineProps<{
    flowId: string;
    nodes: BuilderFlowNode[];
    edges: BuilderFlowEdge[];
    canEdit: boolean;
    /** Validation front des connexions (self-loop, doublon) — déléguée au composable. */
    isValidConnection?: (connection: Connection) => boolean;
}>();

const emit = defineEmits<{
    connect: [connection: BuilderConnection];
    nodeMove: [key: string, x: number, y: number];
    nodeSelect: [key: string];
    edgeSelect: [id: string];
    selectNone: [];
    /** Coordonnées client brutes — la page les convertit en coordonnées flow (screenToFlowCoordinate). */
    dropNode: [type: string, clientPoint: { x: number; y: number }];
}>();

const hasNodes = computed(() => props.nodes.length > 0);

function onConnect(connection: Connection): void {
    emit('connect', {
        source: connection.source,
        target: connection.target,
        sourceHandle: connection.sourceHandle ?? null,
    });
}

function onDrop(event: DragEvent): void {
    const type = event.dataTransfer?.getData('application/x-faucon-node');
    if (!type) {
        return;
    }
    emit('dropNode', type, { x: event.clientX, y: event.clientY });
}
</script>

<template>
    <div
        class="bg-card relative min-w-0 flex-1 overflow-hidden"
        data-test="builder-canvas"
    >
        <VueFlow
            :id="flowId"
            :nodes="nodes"
            :edges="edges"
            :nodes-connectable="canEdit"
            :edges-connectable="false"
            :elements-selectable="canEdit"
            :delete-key-code="null"
            :min-zoom="0.3"
            :max-zoom="2"
            :is-valid-connection="isValidConnection"
            :connection-radius="24"
            fit-view-on-init
            class="builder-flow"
            @connect="onConnect"
            @node-click="(event) => emit('nodeSelect', event.node.id)"
            @edge-click="(event) => emit('edgeSelect', event.edge.id)"
            @pane-click="emit('selectNone')"
            @node-drag-stop="
                (event) =>
                    emit(
                        'nodeMove',
                        event.node.id,
                        event.node.position.x,
                        event.node.position.y,
                    )
            "
            @dragover.prevent
            @drop="onDrop"
        >
            <Background
                :gap="22"
                :size="1.1"
                variant="dots"
                pattern-color="transparent"
            />

            <template #node-workflow="nodeProps">
                <WorkflowNodeCard v-bind="nodeProps" />
            </template>
        </VueFlow>

        <p
            v-if="!hasNodes"
            class="text-muted-foreground pointer-events-none absolute top-50 left-85 text-[13.5px]"
        >
            Déposez un node depuis la palette pour commencer.
        </p>
    </div>
</template>

<style scoped lang="scss">
/*
 * Le canvas vue-flow est entièrement restylé avec les tokens du design
 * system — aucun import de theme-default.css (fidélité maquette).
 */
.builder-flow {
    width: 100%;
    height: 100%;
}

.builder-flow :deep(.vue-flow__background circle) {
    fill: color-mix(in srgb, var(--foreground) 9%, transparent);
}

/* ---- Arêtes (beziers neutres, sélection/hover ambre, flux émeraude) ---- */
.builder-flow :deep(.vue-flow__edge-path) {
    stroke: var(--muted-foreground);
    stroke-width: 1.8;
    opacity: 0.6;
    transition:
        stroke 0.15s ease,
        opacity 0.15s ease,
        stroke-width 0.15s ease;
}

.builder-flow :deep(.vue-flow__edge:hover .vue-flow__edge-path),
.builder-flow :deep(.vue-flow__edge.selected .vue-flow__edge-path) {
    stroke: var(--brand);
    opacity: 1;
    stroke-width: 2.4;
}

.builder-flow :deep(.vue-flow__edge.builder-edge-flowing .vue-flow__edge-path) {
    stroke: var(--cat-4);
    stroke-dasharray: 7 7;
    opacity: 1;
    animation: builder-edge-flow 0.5s linear infinite;
}

.builder-flow :deep(.vue-flow__edge-text) {
    font-size: 9.5px;
    font-weight: 600;
    fill: var(--muted-foreground);
    paint-order: stroke;
    stroke: var(--card);
    stroke-width: 3.5px;
    font-family: var(--font-sans);
}

.builder-flow :deep(.vue-flow__edge-textbg) {
    fill: var(--card);
}

/* ---- Ligne de connexion en cours (dash ambre) ---- */
.builder-flow :deep(.vue-flow__connection-path) {
    stroke: var(--brand);
    stroke-width: 2;
    stroke-dasharray: 5 5;
    opacity: 0.9;
    animation: builder-edge-flow 0.4s linear infinite;
}

/* Le canvas gère le pointeur ; le conteneur reste neutre. */
.builder-flow :deep(.vue-flow__pane) {
    cursor: default;
}

@keyframes builder-edge-flow {
    to {
        stroke-dashoffset: -14;
    }
}
</style>
