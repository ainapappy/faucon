<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import { useVueFlow } from '@vue-flow/core';
import { useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BuilderCanvas from '@/components/builder/BuilderCanvas.vue';
import BuilderTopbar from '@/components/builder/BuilderTopbar.vue';
import ExecDrawer from '@/components/builder/ExecDrawer.vue';
import NodeInspector from '@/components/builder/NodeInspector.vue';
import NodePalette from '@/components/builder/NodePalette.vue';
import { Button } from '@/components/ui/button';
import {
    useWorkflowBuilder,
    type BuilderConnection,
} from '@/composables/useWorkflowBuilder';
import {
    RequestFailure,
    useWorkflowSaver,
    type WorkflowMetadataFields,
} from '@/composables/useWorkflowSaver';
import { useAppearance } from '@/composables/useAppearance';
import {
    useWorkflowSimulation,
    type UseWorkflowSimulationReturn,
} from '@/composables/useWorkflowSimulation';
import workflows, { index as workflowsIndex } from '@/routes/workflows';
import type {
    NodeTypeCatalog,
    WorkflowDetail,
    WorkflowGraph,
    WorkflowGraphPayload,
    WorkflowStatus,
} from '@/types';

type Props = {
    workflow: WorkflowDetail;
    graph: WorkflowGraph;
    nodeTypes: NodeTypeCatalog;
    permissions: {
        canCreateWorkflow: boolean;
        canUpdateWorkflow: boolean;
        canDeleteWorkflow: boolean;
    };
};

const props = defineProps<Props>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

defineOptions({
    layout: (pageProps: {
        currentTeam?: { slug: string } | null;
        workflow?: { name: string };
    }) => ({
        breadcrumbs: [
            {
                title: pageProps.workflow?.name ?? 'Builder',
                href: workflowsIndex({
                    current_team: pageProps.currentTeam?.slug ?? '',
                }).url,
            },
        ],
    }),
});

/*
 * Instance vue-flow partagée : créée ici par id, récupérée par <VueFlow>
 * dans le canvas. La page garde la main sur zoom, fitView et la conversion
 * client → coordonnées flow.
 */
const FLOW_ID = 'workflow-builder';

const { zoomIn, zoomOut, fitView, viewport, screenToFlowCoordinate } =
    useVueFlow(FLOW_ID);

const zoomPercent = computed(() => Math.round(viewport.value.zoom * 100));

/*
 * Modèle du graphe, puis simulation — les deux se référencent par closure
 * (le builder lit les états de simulation pour colorer nodes et arêtes).
 */
let simulation: UseWorkflowSimulationReturn | null = null;

const builder = useWorkflowBuilder(props.nodeTypes, props.graph, {
    getNodeStatus: (key) => simulation?.statuses.value[key] ?? 'idle',
    isEdgeFlowing: (id) => simulation?.flowingEdgeIds.value.has(id) ?? false,
});

simulation = useWorkflowSimulation({
    getNodes: () => builder.nodes.value,
    getEdges: () => builder.edges.value,
    getDefinition: (type) => builder.definitionFor(type),
    onEmpty: () =>
        toast.info('Graphe vide', {
            description:
                'Ajoutez des nodes depuis la palette avant de lancer la simulation.',
        }),
});

/* ---- Panneaux ---- */
const paletteOpen = ref(true);
const inspectorOpen = ref(true);
const journalOpen = ref(false);

/* ---- Thème (le builder est plein écran, sans sidebar) ---- */
const { resolvedAppearance, updateAppearance } = useAppearance();
const isDark = computed(() => resolvedAppearance.value === 'dark');

/*
 * Câblage HTTP (Wayfinder + useHttp). Points d'attention :
 * - useHttp envoie `Accept: application/json` par défaut (Inertia 3.7) —
 *   les 422 arrivent donc bien en JSON dans le bag `errors`.
 * - `useHttp` fonctionne comme useForm : le corps de la requête est l'état
 *   réactif de l'instance (`form.data()`), assigné juste avant l'envoi.
 */
const graphHttp = useHttp<{
    nodes: WorkflowGraphPayload['nodes'];
    edges: WorkflowGraphPayload['edges'];
}>({
    nodes: [],
    edges: [],
});
const metadataHttp = useHttp<{
    name?: string;
    description?: string | null;
    status?: WorkflowStatus;
}>({});

function toRequestFailure(messages: string[]): RequestFailure {
    return new RequestFailure(messages, 'Workflow request failed');
}

async function putGraph(payload: WorkflowGraphPayload): Promise<void> {
    let settled = false;
    await new Promise<void>((resolve, reject) => {
        graphHttp.nodes = payload.nodes;
        graphHttp.edges = payload.edges;
        void graphHttp
            .put(
                workflows.graph.update({
                    current_team: teamSlug.value,
                    workflow: props.workflow.id,
                }).url,
                {
                    onSuccess: () => {
                        settled = true;
                        resolve();
                    },
                    onError: (errors) => {
                        settled = true;
                        reject(toRequestFailure(Object.values(errors)));
                    },
                    onHttpException: (response) => {
                        settled = true;
                        reject(
                            toRequestFailure([
                                `Erreur ${response.status} — session expirée ou serveur indisponible.`,
                            ]),
                        );
                    },
                },
            )
            .catch((error) => {
                if (!settled) {
                    reject(
                        error instanceof RequestFailure
                            ? error
                            : toRequestFailure([
                                  'Erreur réseau lors de l’enregistrement.',
                              ]),
                    );
                }
            });
    });
}

async function patchWorkflow(fields: WorkflowMetadataFields): Promise<void> {
    let settled = false;
    await new Promise<void>((resolve, reject) => {
        Object.assign(metadataHttp, fields);
        void metadataHttp
            .patch(
                workflows.update({
                    current_team: teamSlug.value,
                    workflow: props.workflow.id,
                }).url,
                {
                    onSuccess: () => {
                        settled = true;
                        resolve();
                    },
                    onError: (errors) => {
                        settled = true;
                        reject(toRequestFailure(Object.values(errors)));
                    },
                    onHttpException: (response) => {
                        settled = true;
                        reject(
                            toRequestFailure([
                                `Erreur ${response.status} — session expirée ou serveur indisponible.`,
                            ]),
                        );
                    },
                },
            )
            .catch((error) => {
                if (!settled) {
                    reject(
                        error instanceof RequestFailure
                            ? error
                            : toRequestFailure([
                                  'Erreur réseau lors de l’enregistrement.',
                              ]),
                    );
                }
            });
    });
}

/* ---- Sauvegarde automatique (machine idle → saving → saved | error) ---- */
const canUpdate = computed(() => props.permissions.canUpdateWorkflow);

const saver = useWorkflowSaver({
    getPayload: () => builder.toGraphPayload(),
    isDirty: () => builder.isDirty.value,
    markSynced: (payload) => builder.markSynced(payload),
    putGraph,
    patchWorkflow,
    onError: (messages) =>
        toast.error('Enregistrement impossible', {
            description: messages.slice(0, 3).join(' '),
        }),
});

// Toute mutation du graphe (re)planifie la sauvegarde debouncée.
watch(
    () => JSON.stringify(builder.toGraphPayload()),
    () => {
        if (canUpdate.value) {
            saver.scheduleSave();
        }
    },
);

/* ---- Nom du workflow : commit debouncé par PATCH ---- */
const nameInput = ref(props.workflow.name);

const commitName = useDebounceFn((value) => {
    void saver.saveMetadata({ name: String(value) });
}, 600);

watch(nameInput, (value) => {
    if (value && value !== props.workflow.name) {
        commitName(value);
    }
});

/* ---- Statut du workflow (A3) ---- */
const workflowStatus = ref<WorkflowStatus>(props.workflow.status);
const statusChanging = ref(false);

async function changeStatus(status: WorkflowStatus): Promise<void> {
    if (statusChanging.value || status === workflowStatus.value) {
        return;
    }
    statusChanging.value = true;
    const ok = await saver.saveMetadata({ status });
    statusChanging.value = false;
    if (ok) {
        workflowStatus.value = status;
    }
}

/* ---- Navigation : retour liste, après flush de la sauvegarde ---- */
async function goBack(): Promise<void> {
    await saver.flush();
    router.visit(workflowsIndex({ current_team: teamSlug.value }).url);
}

/* ---- Simulation d'exécution (A1) ---- */
async function run(): Promise<void> {
    if (!simulation || simulation.running.value) {
        return;
    }
    journalOpen.value = true;
    await simulation.start();
    if (simulation.summary.value) {
        toast.success('Exécution réussie (simulation)', {
            description: `${simulation.summary.value.nodes} nodes · ${(simulation.summary.value.durationMs / 1000).toFixed(1)} s`,
        });
    }
}

/* ---- Événements du canvas ---- */
function isValidConnection(connection: {
    source: string;
    target: string;
    sourceHandle?: string | null;
}): boolean {
    return builder.canConnect({
        source: connection.source,
        target: connection.target,
        sourceHandle: connection.sourceHandle ?? null,
    });
}

function onConnect(connection: BuilderConnection): void {
    if (builder.connect(connection)) {
        toast.success('Connexion créée', { duration: 2200 });
    }
}

function onNodeSelect(key: string): void {
    builder.selectNode(key);
    inspectorOpen.value = true;
}

function onDropNode(type: string, clientPoint: { x: number; y: number }): void {
    if (!canUpdate.value) {
        return;
    }
    const definition = builder.definitionFor(type);
    const node = builder.addNode(type, screenToFlowCoordinate(clientPoint));
    if (node && definition) {
        inspectorOpen.value = true;
        toast.success(`« ${definition.label} » ajouté au graphe`, {
            duration: 2200,
        });
    }
}

function onRemoveNode(key: string): void {
    builder.removeNode(key);
    toast.info('Node supprimé');
}

/* ---- Suppression clavier (Delete / Backspace, Escape) ---- */
function onKeydown(event: KeyboardEvent): void {
    const tag = (event.target as HTMLElement | null)?.tagName ?? '';
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return;
    }
    if (event.key === 'Delete' || event.key === 'Backspace') {
        event.preventDefault();
        const selection = builder.selection.value;
        if (!selection || !canUpdate.value) {
            return;
        }
        if (selection.kind === 'node') {
            onRemoveNode(selection.id);
        } else {
            builder.removeEdge(selection.id);
        }
    } else if (event.key === 'Escape') {
        builder.clearSelection();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    // « Exécuter maintenant » depuis la liste : auto-run à l'arrivée (?run=1, A2).
    const query = new URL(window.location.href).searchParams;
    if (query.get('run') === '1') {
        void run();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Head :title="nameInput" />

    <div
        class="bg-background flex h-screen flex-col overflow-hidden"
        data-test="builder-page"
    >
        <BuilderTopbar
            v-model:name="nameInput"
            :status="workflowStatus"
            :save-state="saver.state.value"
            :saved-at="saver.savedAt.value"
            :zoom-percent="zoomPercent"
            :running="simulation.running.value"
            :palette-open="paletteOpen"
            :journal-open="journalOpen"
            :can-update-workflow="permissions.canUpdateWorkflow"
            @back="goBack"
            @run="run"
            @zoom-in="() => zoomIn()"
            @zoom-out="() => zoomOut()"
            @fit-view="() => fitView()"
            @toggle-palette="paletteOpen = !paletteOpen"
            @toggle-journal="journalOpen = !journalOpen"
        />

        <div class="relative flex min-h-0 flex-1">
            <NodePalette :open="paletteOpen" :catalog="nodeTypes" />

            <BuilderCanvas
                :flow-id="FLOW_ID"
                :nodes="builder.vueFlowNodes.value"
                :edges="builder.vueFlowEdges.value"
                :can-edit="permissions.canUpdateWorkflow"
                :is-valid-connection="isValidConnection"
                @connect="onConnect"
                @node-move="(key, x, y) => builder.moveNode(key, x, y)"
                @node-select="onNodeSelect"
                @edge-select="(id) => builder.selectEdge(id)"
                @select-none="builder.clearSelection()"
                @drop-node="onDropNode"
            />

            <NodeInspector
                v-model:open="inspectorOpen"
                :node="builder.selectedNode.value ?? null"
                :definition="
                    builder.selectedNode.value
                        ? (builder.definitionFor(
                              builder.selectedNode.value.type,
                          ) ?? null)
                        : null
                "
                :status="workflowStatus"
                :can-update-workflow="permissions.canUpdateWorkflow"
                @update-node-name="
                    (key, name) => builder.setNodeName(key, name)
                "
                @update-node-config="
                    (key, fieldKey, value) =>
                        builder.setNodeConfig(key, fieldKey, value)
                "
                @remove-node="onRemoveNode"
                @change-status="changeStatus"
            />

            <ExecDrawer
                :open="journalOpen"
                :logs="simulation.logs.value"
                :running="simulation.running.value"
                :summary="simulation.summary.value"
                @close="journalOpen = false"
            />
        </div>

        <!-- Bouton thème : le builder est plein écran, hors shell à sidebar -->
        <Button
            variant="ghost"
            size="icon"
            class="fixed right-3 bottom-3 z-30 h-9 w-9"
            :aria-label="isDark ? 'Thème clair' : 'Thème sombre'"
            @click="updateAppearance(isDark ? 'light' : 'dark')"
        >
            <Sun v-if="isDark" class="h-4 w-4" />
            <Moon v-else class="h-4 w-4" />
        </Button>
    </div>
</template>
