/*
 * Simulation d'exécution du graphe — portage client de la maquette builder
 * (amendement A1 : le « run » de phase 3 est une démo d'UX, 100 % locale).
 *
 * Aucun état backend, aucune route : l'ordre topologique (BFS depuis les
 * sources), les durées aléatoires, les statuts par node, l'animation des
 * arêtes sortantes et le journal horodaté reproduisent `builder.js`.
 * Le journal reste explicite : il s'agit d'une simulation, jamais d'une
 * exécution réelle (moteur persisté : phase 4).
 *
 * Aucune id de type de node en dur : les comportements spéciaux (branche
 * multiple, sortie IA) sont déduits du schéma du catalogue (nombre de
 * sorties, catégorie).
 */
import type { Ref } from 'vue';
import { ref } from 'vue';
import type {
    NodeRunStatus,
    SimulationLogEntry,
    SimulationSummary,
    NodeTypeDefinition,
} from '@/types';

/**
 * Ordre d'exécution : BFS depuis les nodes sans entrée (les déclencheurs),
 * puis les nodes inatteignables en fin de parcours (fidèle à la maquette —
 * tout node s'exécute au moins une fois).
 */
export function computeRunOrder<
    N extends { key: string },
    E extends { sourceNodeKey: string; targetNodeKey: string },
>(nodes: readonly N[], edges: readonly E[]): N[] {
    const targets = new Set(edges.map((edge) => edge.targetNodeKey));
    const queue: string[] = nodes
        .filter((node) => !targets.has(node.key))
        .map((node) => node.key);
    const order: N[] = [];
    const seen = new Set<string>();
    const nodeByKey = new Map(nodes.map((node) => [node.key, node]));

    while (queue.length > 0) {
        const key = queue.shift() as string;
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        const node = nodeByKey.get(key);
        if (node) {
            order.push(node);
        }
        for (const edge of edges) {
            if (edge.sourceNodeKey === key) {
                queue.push(edge.targetNodeKey);
            }
        }
    }

    for (const node of nodes) {
        if (!seen.has(node.key)) {
            order.push(node);
        }
    }

    return order;
}

const sleep = (ms: number) =>
    new Promise<void>((resolve) => setTimeout(resolve, ms));

export type UseWorkflowSimulationOptions = {
    getNodes: () => Array<{ key: string; type: string; name: string }>;
    getEdges: () => Array<{
        id: string;
        sourceNodeKey: string;
        targetNodeKey: string;
        sourceHandle: string | null;
    }>;
    getDefinition: (type: string) => NodeTypeDefinition | undefined;
    /** Prévenu quand le graphe est vide (la page affiche le toast). */
    onEmpty?: () => void;
    /** Injectables pour les tests. */
    wait?: (ms: number) => Promise<void>;
    random?: () => number;
};

export type UseWorkflowSimulationReturn = {
    running: Ref<boolean>;
    statuses: Ref<Record<string, NodeRunStatus>>;
    flowingEdgeIds: Ref<Set<string>>;
    logs: Ref<SimulationLogEntry[]>;
    summary: Ref<SimulationSummary | null>;
    start: () => Promise<void>;
    reset: () => void;
};

export function useWorkflowSimulation(
    options: UseWorkflowSimulationOptions,
): UseWorkflowSimulationReturn {
    const wait = options.wait ?? sleep;
    const random = options.random ?? Math.random;

    const running = ref(false);
    const statuses = ref<Record<string, NodeRunStatus>>({});
    const flowingEdgeIds = ref<Set<string>>(new Set());
    const logs = ref<SimulationLogEntry[]>([]);
    const summary = ref<SimulationSummary | null>(null);

    let startedAt = 0;

    function elapsedSeconds(): string {
        const seconds = (Date.now() - startedAt) / 1000;
        return `${seconds.toFixed(2)}s`;
    }

    function log(
        source: string,
        message: string,
        level: SimulationLogEntry['level'] = 'info',
    ): void {
        logs.value.push({ time: elapsedSeconds(), source, message, level });
    }

    function reset(): void {
        statuses.value = Object.fromEntries(
            options.getNodes().map((node) => [node.key, 'idle' as const]),
        );
    }

    async function start(): Promise<void> {
        if (running.value) {
            return;
        }
        const nodes = options.getNodes();
        const edges = options.getEdges();

        if (nodes.length === 0) {
            // Graphe vide : journal + notification, jamais d'erreur bloquante (A1/A2).
            logs.value = [];
            startedAt = Date.now();
            log(
                'système',
                'Le graphe est vide — ajoutez des nodes avant d’exécuter.',
            );
            options.onEmpty?.();
            return;
        }

        running.value = true;
        logs.value = [];
        summary.value = null;
        flowingEdgeIds.value = new Set();
        statuses.value = Object.fromEntries(
            nodes.map((node) => [node.key, 'idle' as const]),
        );
        startedAt = Date.now();

        log(
            'système',
            `Validation du graphe… ${nodes.length} nodes, ${edges.length} arêtes — OK`,
        );
        await wait(420);
        log(
            'système',
            'Exécution de test démarrée — simulation locale, aucune donnée réelle.',
        );

        const order = computeRunOrder(nodes, edges);

        for (const node of order) {
            const definition = options.getDefinition(node.type);
            const label = definition?.label ?? node.type;

            statuses.value[node.key] = 'running';
            log(label, `Démarrage — ${node.name}`);

            const durationMs = 380 + random() * 720;
            await wait(durationMs);

            statuses.value[node.key] = 'ok';
            const outgoing = edges.filter(
                (edge) => edge.sourceNodeKey === node.key,
            );
            if (outgoing.length > 0) {
                const flowing = new Set(flowingEdgeIds.value);
                for (const edge of outgoing) {
                    flowing.add(edge.id);
                }
                flowingEdgeIds.value = flowing;
                setTimeout(() => {
                    const next = new Set(flowingEdgeIds.value);
                    for (const edge of outgoing) {
                        next.delete(edge.id);
                    }
                    flowingEdgeIds.value = next;
                }, 1400);
            }
            log(label, `Terminé en ${Math.round(durationMs)} ms`, 'ok');

            // Node multi-branches (ex. deux sorties « true / false ») : la simulation
            // retient la première branche et marque la cible de l'autre comme non
            // parcourue — comportement de la maquette, déduit du schéma du catalogue.
            if (definition && definition.outputs.length > 1) {
                const taken = definition.outputs[0];
                log(
                    label,
                    `Évaluation → branche « ${taken.label ?? taken.id} »`,
                );
                const skipped = definition.outputs[1];
                if (skipped) {
                    const skippedEdge = edges.find(
                        (edge) =>
                            edge.sourceNodeKey === node.key &&
                            edge.sourceHandle === skipped.id,
                    );
                    const skippedNode = skippedEdge
                        ? nodes.find(
                              (candidate) =>
                                  candidate.key === skippedEdge.targetNodeKey,
                          )
                        : undefined;
                    if (skippedNode) {
                        statuses.value[skippedNode.key] = 'idle';
                    }
                }
            }

            if (definition?.category === 'ai') {
                log(
                    label,
                    'Sortie simulée (aperçu) — aucune donnée réelle n’est traitée.',
                    'ok',
                );
            }

            await wait(120);
        }

        const totalMs = Date.now() - startedAt;
        summary.value = { ok: true, nodes: order.length, durationMs: totalMs };
        log(
            'système',
            `Exécution terminée en ${(totalMs / 1000).toFixed(1)} s`,
            'ok',
        );
        running.value = false;
    }

    return {
        running,
        statuses,
        flowingEdgeIds,
        logs,
        summary,
        start,
        reset,
    };
}
