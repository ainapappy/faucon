/*
 * Variables interpolables des nodes IA (phase 6, lot G).
 *
 * Le contexte d'interpolation du moteur expose le payload du déclencheur sous
 * `trigger`, la sortie de chaque node sous sa clé (`{{ <clé>.<sortie> }}`) et
 * la sortie des nodes `data.input` sous le nom de variable configuré
 * (exposeAs, défaut du placeholder : `payload`). L'aide-mémoire de
 * l'inspecteur IA liste ces chemins RÉELS : les clés de sortie des modes IA
 * sont connues du front (contrat moteur — text|label|structured + usage),
 * tout autre node amont est proposé en générique.
 *
 * Pure logique (lib), testable hors navigateur — les composants ne
 * transforment jamais les props eux-mêmes.
 */
import type { WorkflowNodeData } from '@/types';

/** Clés de sortie d'un node IA par type id (la clé `usage` accompagne toujours la sortie du mode). */
export const aiOutputKeysByType: Record<string, readonly string[]> = {
    'ai.prompt': ['text', 'usage'],
    'ai.classification': ['label', 'usage'],
    'ai.extraction': ['structured', 'usage'],
    'ai.summarization': ['text', 'usage'],
    'ai.generation': ['text', 'usage'],
};

const AI_SEGMENT = 'ai.';
const TRIGGER_SEGMENT = 'trigger.';

/** Nom de variable exposé par un `data.input` sans nom configuré (défaut du placeholder). */
const DEFAULT_EXPOSED_NAME = 'payload';

/** Un type du catalogue est-il un node IA (`ai.*`) ? */
export function isAiNodeType(typeId: string): boolean {
    return typeId.startsWith(AI_SEGMENT);
}

/** Hints FR par mode IA (présentation d'aide — les modes viennent du catalogue). */
const AI_MODE_HINTS: Record<string, string> = {
    classification:
        'Séparez les étiquettes par des virgules — le modèle en choisit exactement une.',
    extraction:
        'Une ligne par champ, au format « clé: type » — types admis : text, number, boolean.',
};

/** Hint du mode IA pour l'inspecteur, null quand le mode n'en a pas (ou hors IA). */
export function aiModeHint(typeId: string): string | null {
    if (!isAiNodeType(typeId)) {
        return null;
    }
    return AI_MODE_HINTS[typeId.slice(AI_SEGMENT.length)] ?? null;
}

/** Forme structurelle minimale acceptée (node du builder comme du graphe persisté). */
type NodeLike = Pick<WorkflowNodeData, 'key' | 'type' | 'config'>;
type EdgeLike = { sourceNodeKey: string; targetNodeKey: string };

/**
 * Chemins interpolables suggérés pour un node : le contexte `trigger` en tête,
 * puis les ancêtres en BFS sur les arêtes entrants — clés de sortie réelles
 * pour les modes IA connus, nom de variable exposée pour `data.input`,
 * chemin générique `{{ <clé>.… }}` pour les autres.
 */
export function upstreamVariablePaths(
    nodeKey: string,
    nodes: readonly NodeLike[],
    edges: readonly EdgeLike[],
): string[] {
    const nodeByKey = new Map(nodes.map((node) => [node.key, node]));

    const parentsOf = new Map<string, string[]>();
    for (const edge of edges) {
        const parents = parentsOf.get(edge.targetNodeKey) ?? [];
        parents.push(edge.sourceNodeKey);
        parentsOf.set(edge.targetNodeKey, parents);
    }

    const paths: string[] = ['{{ trigger.… }}'];

    const visited = new Set<string>();
    const queue = [...(parentsOf.get(nodeKey) ?? [])];
    while (queue.length > 0) {
        const key = queue.shift() as string;
        if (visited.has(key)) {
            continue;
        }
        visited.add(key);
        for (const parent of parentsOf.get(key) ?? []) {
            queue.push(parent);
        }

        const node = nodeByKey.get(key);
        if (!node) {
            continue;
        }

        // Le payload du déclencheur vit sous le contexte `trigger` (déjà listé en tête).
        if (node.type.startsWith(TRIGGER_SEGMENT)) {
            continue;
        }

        if (node.type === 'data.input') {
            const exposedName =
                String(node.config.name ?? '').trim() || DEFAULT_EXPOSED_NAME;
            paths.push(`{{ ${exposedName}.… }}`);
            continue;
        }

        const outputKeys = aiOutputKeysByType[node.type];
        if (outputKeys) {
            for (const outputKey of outputKeys) {
                paths.push(`{{ ${key}.${outputKey} }}`);
            }
            continue;
        }

        paths.push(`{{ ${key}.… }}`);
    }

    return paths;
}
