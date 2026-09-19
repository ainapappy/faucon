/*
 * Types du domaine Workflows (phase 3 — Workflow Builder).
 *
 * Miroir exact des props Inertia envoyées par les contrôleurs
 * `App\Http\Controllers\Workflows\*` et du catalogue backend
 * `App\Services\Workflow\NodeCatalog`. Ne jamais redéclarer ces
 * formes localement : importer depuis `@/types`.
 */

/** Statut persisté en base — les libellés FR (« Actif » / « En pause ») sont une couche de présentation. */
export type WorkflowStatus = 'draft' | 'active';

/** Les cinq catégories de nodes (ordre fixe validé CVD, voir `lib/nodeCategories.ts`). */
export type NodeCategory = 'trigger' | 'data' | 'logic' | 'ai' | 'action';

/** Types de champs de configuration rendus par l'inspecteur. */
export type NodeFieldType = 'text' | 'textarea' | 'select' | 'range';

/** Valeurs de configuration acceptées par le backend (scalaires uniquement). */
export type NodeConfigValue = string | number | boolean;

/** Sortie d'un node (port source) — `position` = ancrage vertical en fraction (0→1). */
export type NodePort = {
    id: string;
    label?: string | null;
    position: number;
};

/** Champ de configuration déclaré par le catalogue pour un type de node. */
export type NodeField = {
    key: string;
    label: string;
    type: NodeFieldType;
    required: boolean;
    placeholder?: string | null;
    options?: string[] | null;
    min?: number | null;
    max?: number | null;
    step?: number | null;
    mono: boolean;
};

/** Définition d'un type de node, sérialisée depuis `NodeCatalog` (prop `nodeTypes`). */
export type NodeTypeDefinition = {
    type: string;
    category: NodeCategory;
    label: string;
    description: string;
    icon: string;
    input: boolean;
    outputs: NodePort[];
    fields: NodeField[];
};

/** Catalogue servi en prop, indexé par identifiant de type (`category.type`). */
export type NodeTypeCatalog = Record<string, NodeTypeDefinition>;

/** Ligne de la liste des workflows (contrôleur `WorkflowController@index`). */
export type WorkflowListItem = {
    id: number;
    name: string;
    description: string | null;
    status: WorkflowStatus;
    nodesCount: number;
    triggerType: string | null;
};

/** Node du graphe persisté (prop `graph.nodes`). */
export type WorkflowNodeData = {
    key: string;
    type: string;
    name: string;
    config: Record<string, NodeConfigValue>;
    positionX: number;
    positionY: number;
};

/** Arête du graphe persisté — `id` sert uniquement de clé de rendu, il n'est pas renvoyé au PUT. */
export type WorkflowEdgeData = {
    id: number;
    sourceNodeKey: string;
    targetNodeKey: string;
    sourceHandle: string | null;
};

/** Graphe complet reçu en prop `graph`. */
export type WorkflowGraph = {
    nodes: WorkflowNodeData[];
    edges: WorkflowEdgeData[];
};

/** Workflow détaillé du builder (prop `workflow`). */
export type WorkflowDetail = {
    id: number;
    name: string;
    description: string | null;
    status: WorkflowStatus;
};

/** Payload d'écriture du graphe — PUT `workflows.graph.update` (les deux clés toujours présentes). */
export type WorkflowGraphPayload = {
    nodes: Array<{
        key: string;
        type: string;
        name: string;
        config: Record<string, NodeConfigValue>;
        positionX: number;
        positionY: number;
    }>;
    edges: Array<{
        sourceNodeKey: string;
        targetNodeKey: string;
        sourceHandle: string | null;
    }>;
};

/** Machine à états de la sauvegarde automatique du graphe. */
export type SaveState = 'idle' | 'saving' | 'saved' | 'error';

/** Statut d'exécution d'un node pendant la simulation (A1 — run simulé, client-side uniquement). */
export type NodeRunStatus = 'idle' | 'running' | 'ok' | 'error';

/** Entrée horodatée du journal d'exécution (tiroir du builder). */
export type SimulationLogEntry = {
    /** Temps écoulé depuis le lancement, ex. « 1,42 s ». */
    time: string;
    /** Source du log (« système » ou libellé du type de node). */
    source: string;
    message: string;
    level: 'info' | 'ok' | 'error';
};

/** Résumé de fin de simulation. */
export type SimulationSummary = {
    ok: boolean;
    nodes: number;
    durationMs: number;
};
