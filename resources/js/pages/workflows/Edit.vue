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
import TestRunDialog from '@/components/builder/TestRunDialog.vue';
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
    formatRunSeconds,
    useWorkflowTestRun,
    type UseWorkflowTestRunReturn,
} from '@/composables/useWorkflowTestRun';
import workflows, { index as workflowsIndex } from '@/routes/workflows';
import type {
    ExecutionResult,
    NodeRunResult,
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
 * Modèle du graphe, puis test réel — les deux se référencent par closure
 * (le builder lit les états du test pour colorer nodes et arêtes).
 */
let testRun: UseWorkflowTestRunReturn | null = null;

const builder = useWorkflowBuilder(props.nodeTypes, props.graph, {
    getNodeStatus: (key) => testRun?.statuses.value[key] ?? 'idle',
    isEdgeFlowing: (id) => testRun?.flowingEdgeIds.value.has(id) ?? false,
});

/* ---- Panneaux ---- */
const paletteOpen = ref(true);
const inspectorOpen = ref(true);
const journalOpen = ref(false);
const testRunDialogOpen = ref(false);

/** Échantillon proposé par la modale (maquette builder.html). */
const defaultSampleInput: Record<string, unknown> = {
    email: 'client@example.com',
    name: 'Aina',
    message: 'Bonjour, je souhaite un devis pour le pack Pro.',
};

const selectedNodeResult = computed<NodeRunResult | null>(() => {
    const key = builder.selectedNode.value?.key;
    return (key && testRun?.resultsByKey.value.get(key)) || null;
});

testRun = useWorkflowTestRun({
    postTestRun,
    getNodes: () => builder.nodes.value,
    getEdges: () => builder.edges.value,
    // La réponse est là : la modale se ferme et le tiroir s'ouvre pour la relecture.
    onRunStarted: () => {
        testRunDialogOpen.value = false;
        journalOpen.value = true;
    },
    onRequestError: (messages) =>
        toast.error('Test impossible', {
            // La modale reste ouverte pour corriger l'échantillon (master §20).
            description: messages.slice(0, 3).join(' '),
        }),
});

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

/* ---- Test réel du workflow (phase 4, U2) : modale d'échantillon → run ---- */
const testRunHttp = useHttp<{ input: string }, ExecutionResult>({ input: '' });

async function postTestRun(
    sample: Record<string, unknown>,
): Promise<ExecutionResult> {
    let settled = false;
    return new Promise<ExecutionResult>((resolve, reject) => {
        // Le contrat attend la chaîne JSON, pas un objet.
        testRunHttp.input = JSON.stringify(sample);
        void testRunHttp
            .post(
                workflows.testRun({
                    current_team: teamSlug.value,
                    workflow: props.workflow.id,
                }).url,
                {
                    onSuccess: (response) => {
                        settled = true;
                        resolve(response);
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
                                  'Erreur réseau pendant le test.',
                              ]),
                    );
                }
            });
    });
}

function openTestDialog(): void {
    if (testRun?.state.value === 'running') {
        return;
    }
    testRunDialogOpen.value = true;
}

async function launchTest(sample: Record<string, unknown>): Promise<void> {
    if (!testRun) {
        return;
    }
    await testRun.start(sample);

    const result = testRun.result.value;
    if (!result) {
        // 422 / erreur réseau : déjà toastée via onRequestError, modale toujours ouverte.
        return;
    }
    if (result.status === 'completed') {
        toast.success('Test réussi', {
            description: `${result.nodes.length} nodes · ${formatRunSeconds(result.durationMs)}`,
        });
        return;
    }
    toast.error('Test échoué', {
        description:
            result.errors[0]?.message ??
            'Le workflow n’est pas exécutable en l’état.',
    });
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

    // « Exécuter maintenant » depuis la liste (AM2) : ouvre la modale
    // d'échantillon — le run réel exige un input, plus d'auto-run silencieux.
    const query = new URL(window.location.href).searchParams;
    if (query.get('run') === '1') {
        openTestDialog();
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
            :running="testRun.state.value === 'running'"
            :palette-open="paletteOpen"
            :journal-open="journalOpen"
            :can-update-workflow="permissions.canUpdateWorkflow"
            @back="goBack"
            @run="openTestDialog"
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
                :node-result="selectedNodeResult"
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
                :state="testRun.state.value"
                :result="testRun.result.value"
                :statuses="testRun.statuses.value"
                @close="journalOpen = false"
            />
        </div>

        <!-- Modale « Tester » : input d'échantillon requis avant tout run (U2/AM2). -->
        <TestRunDialog
            v-model:open="testRunDialogOpen"
            :sample-input="defaultSampleInput"
            :launching="testRun.state.value === 'running'"
            @launch="launchTest"
        />

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
