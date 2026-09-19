/*
 * Modèle du graphe du builder — source de vérité unique de l'éditeur.
 *
 * Volontairement indépendant de vue-flow : le composable ne manipule que
 * des données, ce qui le rend testable hors navigateur (Vitest). Le pont
 * vers vue-flow est assuré par les computed `vueFlowNodes` / `vueFlowEdges`
 * (formes d'objets compatibles, sans import runtime) et par les événements
 * du canvas (`@connect`, `@node-drag-stop`…) qui rappellent les mutations.
 *
 * Point unique d'arrondi des positions : `moveNode` et `addNode` arrondissent
 * à l'entier — aucun autre endroit du front ne doit arrondir, sinon le graphe
 * redevient « dirty » après chaque rechargement.
 */
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type {
    NodeConfigValue,
    NodeField,
    NodeRunStatus,
    NodeTypeCatalog,
    NodeTypeDefinition,
    WorkflowEdgeData,
    WorkflowGraph,
    WorkflowGraphPayload,
    WorkflowNodeData,
} from '@/types';

/** Dimensions des nodes du builder (maquette : 190 × 78 px). */
export const NODE_WIDTH = 190;
export const NODE_HEIGHT = 78;

/** Sélection courante du canvas (un node, une arête, ou rien). */
export type BuilderSelection = { kind: 'node' | 'edge'; id: string } | null;

/** Node du modèle interne (identique au payload d'écriture). */
export type BuilderNode = WorkflowNodeData;

/** Arête du modèle interne — l'`id` est généré côté client et n'est jamais envoyé. */
export type BuilderEdge = {
    id: string;
    sourceNodeKey: string;
    targetNodeKey: string;
    sourceHandle: string | null;
};

/** Connexion proposée par le canvas (ports de vue-flow). */
export type BuilderConnection = {
    source: string;
    target: string;
    sourceHandle: string | null;
};

/** Injection des états de simulation pour colorer nodes/arêtes sans couplage direct. */
export type BuilderSimulationHooks = {
    getNodeStatus?: (key: string) => NodeRunStatus;
    isEdgeFlowing?: (id: string) => boolean;
};

/** Forme minimale consommée par `<VueFlow>` (compatible `Node` de @vue-flow/core). */
export type BuilderFlowNode = {
    id: string;
    type: string;
    position: { x: number; y: number };
    selected: boolean;
    data: {
        node: BuilderNode;
        definition: NodeTypeDefinition | null;
        status: NodeRunStatus;
    };
};

/** Forme minimale consommée par `<VueFlow>` (compatible `Edge` de @vue-flow/core). */
export type BuilderFlowEdge = {
    id: string;
    source: string;
    target: string;
    sourceHandle?: string | null;
    label?: string;
    selected: boolean;
    class?: string;
    data: {
        definition: NodeTypeDefinition | null;
    };
};

let keyCounter = 0;

/** Clé UUID pour nodes et arêtes créés côté client. */
export function newNodeKey(): string {
    keyCounter += 1;
    const cryptoRef = typeof crypto !== 'undefined' ? crypto : undefined;
    if (cryptoRef && typeof cryptoRef.randomUUID === 'function') {
        return cryptoRef.randomUUID();
    }
    return `key-${Date.now().toString(36)}-${keyCounter}`;
}

/** Valeur de config initiale d'un champ, déduite du schéma du catalogue. */
export function defaultConfigValue(field: NodeField): NodeConfigValue {
    if (field.type === 'range') {
        const min = field.min ?? 0;
        const max = field.max ?? 1;
        return min + (max - min) / 2;
    }
    if (field.type === 'select') {
        return field.options?.[0] ?? '';
    }
    return '';
}

function fromGraph(graph: WorkflowGraph): {
    nodes: BuilderNode[];
    edges: BuilderEdge[];
} {
    return {
        nodes: graph.nodes.map((node) => ({
            key: node.key,
            type: node.type,
            name: node.name,
            config: { ...node.config },
            positionX: Number(node.positionX),
            positionY: Number(node.positionY),
        })),
        edges: graph.edges.map((edge: WorkflowEdgeData) => ({
            id: String(edge.id),
            sourceNodeKey: edge.sourceNodeKey,
            targetNodeKey: edge.targetNodeKey,
            sourceHandle: edge.sourceHandle,
        })),
    };
}

export type UseWorkflowBuilderReturn = {
    nodes: Ref<BuilderNode[]>;
    edges: Ref<BuilderEdge[]>;
    selection: Ref<BuilderSelection>;
    selectedNode: ComputedRef<BuilderNode | undefined>;
    selectedEdge: ComputedRef<BuilderEdge | undefined>;
    isDirty: ComputedRef<boolean>;
    nodeCount: ComputedRef<number>;
    vueFlowNodes: ComputedRef<BuilderFlowNode[]>;
    vueFlowEdges: ComputedRef<BuilderFlowEdge[]>;
    definitionFor: (type: string) => NodeTypeDefinition | undefined;
    addNode: (
        type: string,
        position: { x: number; y: number },
    ) => BuilderNode | null;
    removeNode: (key: string) => void;
    removeEdge: (id: string) => void;
    connect: (connection: BuilderConnection) => boolean;
    canConnect: (connection: BuilderConnection) => boolean;
    moveNode: (key: string, positionX: number, positionY: number) => void;
    setNodeName: (key: string, name: string) => void;
    setNodeConfig: (
        key: string,
        fieldKey: string,
        value: NodeConfigValue,
    ) => void;
    selectNode: (key: string) => void;
    selectEdge: (id: string) => void;
    clearSelection: () => void;
    toGraphPayload: () => WorkflowGraphPayload;
    markSynced: (payload?: WorkflowGraphPayload) => void;
};

export function useWorkflowBuilder(
    catalog: NodeTypeCatalog,
    graph: WorkflowGraph,
    simulationHooks: BuilderSimulationHooks = {},
): UseWorkflowBuilderReturn {
    const initial = fromGraph(graph);
    const nodes = ref<BuilderNode[]>(initial.nodes);
    const edges = ref<BuilderEdge[]>(initial.edges);
    const selection = ref<BuilderSelection>(null);

    /*
     * Baseline de propreté : le DERNIER payload connu du serveur. Après un
     * PUT réussi, `markSynced(payload)` fige le payload envoyé — un changement
     * survenu pendant la sauvegarde reste donc « dirty » et sera renvoyé.
     */
    const baselinePayload = ref<WorkflowGraphPayload>(toGraphPayload());

    const definitionFor = (type: string): NodeTypeDefinition | undefined =>
        catalog[type];

    const nodeById = (key: string) =>
        nodes.value.find((node) => node.key === key);

    const selectedNode = computed(() =>
        selection.value?.kind === 'node'
            ? nodeById(selection.value.id)
            : undefined,
    );

    const selectedEdge = computed(() =>
        selection.value?.kind === 'edge'
            ? edges.value.find((edge) => edge.id === selection.value?.id)
            : undefined,
    );

    const isDirty = computed(
        () =>
            JSON.stringify(toGraphPayload()) !==
            JSON.stringify(baselinePayload.value),
    );

    const nodeCount = computed(() => nodes.value.length);

    const vueFlowNodes = computed<BuilderFlowNode[]>(() =>
        nodes.value.map((node) => ({
            id: node.key,
            type: 'workflow',
            position: { x: node.positionX, y: node.positionY },
            selected:
                selection.value?.kind === 'node' &&
                selection.value.id === node.key,
            data: {
                node,
                definition: definitionFor(node.type) ?? null,
                status: simulationHooks.getNodeStatus?.(node.key) ?? 'idle',
            },
        })),
    );

    const vueFlowEdges = computed<BuilderFlowEdge[]>(() =>
        edges.value.map((edge) => {
            const source = nodeById(edge.sourceNodeKey);
            return {
                id: edge.id,
                source: edge.sourceNodeKey,
                target: edge.targetNodeKey,
                sourceHandle: edge.sourceHandle,
                label:
                    edge.sourceHandle !== null && edge.sourceHandle !== 'out'
                        ? edge.sourceHandle
                        : undefined,
                selected:
                    selection.value?.kind === 'edge' &&
                    selection.value.id === edge.id,
                class: simulationHooks.isEdgeFlowing?.(edge.id)
                    ? 'builder-edge-flowing'
                    : undefined,
                data: {
                    definition: source
                        ? (definitionFor(source.type) ?? null)
                        : null,
                },
            };
        }),
    );

    function selectNode(key: string): void {
        selection.value = { kind: 'node', id: key };
    }

    function selectEdge(id: string): void {
        selection.value = { kind: 'edge', id };
    }

    function clearSelection(): void {
        selection.value = null;
    }

    function addNode(
        type: string,
        position: { x: number; y: number },
    ): BuilderNode | null {
        const definition = definitionFor(type);
        if (!definition) {
            return null;
        }
        const config: Record<string, NodeConfigValue> = {};
        for (const field of definition.fields) {
            config[field.key] = defaultConfigValue(field);
        }
        const node: BuilderNode = {
            key: newNodeKey(),
            type,
            name: definition.label,
            config,
            // Arrondi unique (avec moveNode) : le payload reste stable après rechargement.
            positionX: Math.round(position.x - NODE_WIDTH / 2),
            positionY: Math.round(position.y - NODE_HEIGHT / 2),
        };
        nodes.value.push(node);
        selectNode(node.key);
        return node;
    }

    function removeNode(key: string): void {
        // Cascade : les arêtes attachées au node disparaissent avec lui.
        edges.value = edges.value.filter(
            (edge) => edge.sourceNodeKey !== key && edge.targetNodeKey !== key,
        );
        nodes.value = nodes.value.filter((node) => node.key !== key);
        if (selection.value?.kind === 'node' && selection.value.id === key) {
            selection.value = null;
        }
    }

    function removeEdge(id: string): void {
        edges.value = edges.value.filter((edge) => edge.id !== id);
        if (selection.value?.kind === 'edge' && selection.value.id === id) {
            selection.value = null;
        }
    }

    /**
     * Validation pure d'une connexion (utilisée aussi par `isValidConnection`
     * du canvas) : pas de self-loop, pas de doublon (source, cible, handle),
     * handle existant sur le type source.
     */
    function canConnect(connection: BuilderConnection): boolean {
        if (connection.source === connection.target) {
            return false;
        }
        if (!connection.source || !connection.target) {
            return false;
        }
        if (
            edges.value.some(
                (edge) =>
                    edge.sourceNodeKey === connection.source &&
                    edge.targetNodeKey === connection.target &&
                    edge.sourceHandle === connection.sourceHandle,
            )
        ) {
            return false;
        }
        const source = nodeById(connection.source);
        if (!source) {
            return false;
        }
        const definition = definitionFor(source.type);
        if (
            definition &&
            connection.sourceHandle !== null &&
            !definition.outputs.some(
                (port) => port.id === connection.sourceHandle,
            )
        ) {
            return false;
        }
        return true;
    }

    function connect(connection: BuilderConnection): boolean {
        if (!canConnect(connection)) {
            return false;
        }
        edges.value.push({
            id: newNodeKey(),
            sourceNodeKey: connection.source,
            targetNodeKey: connection.target,
            sourceHandle: connection.sourceHandle,
        });
        return true;
    }

    function moveNode(key: string, positionX: number, positionY: number): void {
        const node = nodeById(key);
        if (!node) {
            return;
        }
        // Point unique d'arrondi des positions du front.
        node.positionX = Math.round(positionX);
        node.positionY = Math.round(positionY);
    }

    function setNodeName(key: string, name: string): void {
        const node = nodeById(key);
        if (node) {
            node.name = name;
        }
    }

    function setNodeConfig(
        key: string,
        fieldKey: string,
        value: NodeConfigValue,
    ): void {
        const node = nodeById(key);
        if (node) {
            node.config = { ...node.config, [fieldKey]: value };
        }
    }

    function toGraphPayload(): WorkflowGraphPayload {
        return {
            nodes: nodes.value.map((node) => ({
                key: node.key,
                type: node.type,
                name: node.name,
                config: { ...node.config },
                positionX: node.positionX,
                positionY: node.positionY,
            })),
            edges: edges.value.map((edge) => ({
                sourceNodeKey: edge.sourceNodeKey,
                targetNodeKey: edge.targetNodeKey,
                sourceHandle: edge.sourceHandle,
            })),
        };
    }

    function markSynced(payload?: WorkflowGraphPayload): void {
        baselinePayload.value = payload ?? toGraphPayload();
    }

    return {
        nodes,
        edges,
        selection,
        selectedNode,
        selectedEdge,
        isDirty,
        nodeCount,
        vueFlowNodes,
        vueFlowEdges,
        definitionFor,
        addNode,
        removeNode,
        removeEdge,
        connect,
        canConnect,
        moveNode,
        setNodeName,
        setNodeConfig,
        selectNode,
        selectEdge,
        clearSelection,
        toGraphPayload,
        markSynced,
    };
}
