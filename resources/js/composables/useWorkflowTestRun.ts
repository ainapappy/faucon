/*
 * Test réel d'un workflow (phase 4) — remplace la simulation client (U2).
 *
 * Machine à états `idle → running → completed | failed` : le POST
 * `workflows.test-run` renvoie TOUJOURS un résultat (un échec de validation
 * est un résultat, pas une erreur HTTP) ; seule une 422 / erreur réseau
 * ramène à `idle` (la modale d'échantillon reste exploitable).
 *
 * La réponse est rejouée ANIMÉMENT dans l'ordre d'exécution, comme la
 * maquette : chaque node exécuté passe brièvement `running` puis applique
 * son statut final (`ok`/`error`), ses arêtes sortantes vers un node
 * exécuté « coulent » ~1,4 s ; les `skipped` sont appliqués sans délai
 * (aspect repos, comme la maquette qui remet le node non pris à idle).
 * Toutes les données (statuts, durées, sorties) viennent de la réponse
 * serveur — rien d'aléatoire.
 *
 * Couche HTTP injectée : le composable reste testable hors navigateur
 * (Vitest) et la page reste un simple câblage `useHttp` + Wayfinder.
 */
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { RequestFailure } from '@/composables/useWorkflowSaver';
import type {
    ExecutionResult,
    NodeRunResult,
    NodeRunStatus,
    TestRunState,
} from '@/types';

/** Délai entre deux révélations de node pendant la relecture (maquette). */
const DEFAULT_REVEAL_STEP_MS = 120;

/** Plafond total de la relecture : la réponse est déjà connue, l'animation ne doit pas traîner. */
const REVEAL_CAP_MS = 1500;

/** Durée d'affichage des arêtes « en flux » après un node réussi (maquette : ~1,4 s). */
const EDGE_FLOW_MS = 1400;

const GENERIC_REQUEST_ERROR =
    'Le test n’a pas pu être lancé — vérifiez votre connexion puis réessayez.';

/**
 * Parse l'input d'échantillon de la modale « Tester » — miroir du 422
 * backend (objet JSON strict) : mêmes messages, la correction est immédiate.
 */
export function parseSampleInput(text: string): {
    sample: Record<string, unknown> | null;
    error: string | null;
} {
    const trimmed = text.trim();
    if (trimmed === '') {
        return { sample: null, error: null };
    }

    let parsed: unknown;
    try {
        parsed = JSON.parse(trimmed);
    } catch (error) {
        const detail =
            error instanceof Error ? error.message : 'erreur de syntaxe';
        return { sample: null, error: `JSON invalide — ${detail}` };
    }

    if (
        parsed === null ||
        typeof parsed !== 'object' ||
        Array.isArray(parsed)
    ) {
        return { sample: null, error: 'L’input doit être un objet JSON.' };
    }

    return { sample: parsed as Record<string, unknown>, error: null };
}

/** Durée d'un run au format de la maquette (« 1.2 s »). */
export function formatRunSeconds(durationMs: number): string {
    return `${(durationMs / 1000).toFixed(1)} s`;
}

/**
 * Sortie d'un node rendue en TEXTE (jamais v-html — les données viennent du
 * graphe utilisateur). Partagé par le tiroir de résultats et l'inspecteur.
 */
export function formatNodeOutput(output: Record<string, unknown>): string {
    return JSON.stringify(output, null, 2);
}

function toErrorMessages(error: unknown): string[] {
    if (error instanceof RequestFailure && error.messages.length > 0) {
        return error.messages;
    }
    return [GENERIC_REQUEST_ERROR];
}

export type UseWorkflowTestRunOptions = {
    /** POST `workflows.test-run` — câblage `useHttp` + Wayfinder de la page. */
    postTestRun: (
        sampleInput: Record<string, unknown>,
    ) => Promise<ExecutionResult>;
    getNodes: () => Array<{ key: string }>;
    getEdges: () => Array<{
        id: string;
        sourceNodeKey: string;
        targetNodeKey: string;
        sourceHandle: string | null;
    }>;
    /** 422 (bag d'erreurs) ou erreur réseau — toast côté page. */
    onRequestError?: (messages: string[]) => void;
    /** Notifié à la réception de la réponse, AVANT la relecture (fermeture de la modale côté page). */
    onRunStarted?: (result: ExecutionResult) => void;
    /** Relecture animée (injectable pour les tests). */
    wait?: (ms: number) => Promise<void>;
    revealStepMs?: number;
};

export type UseWorkflowTestRunReturn = {
    state: Ref<TestRunState>;
    result: Ref<ExecutionResult | null>;
    /** Alimente les hooks du builder (statuts des cartes et du tiroir). */
    statuses: Ref<Record<string, NodeRunStatus>>;
    flowingEdgeIds: Ref<Set<string>>;
    resultsByKey: ComputedRef<Map<string, NodeRunResult>>;
    start: (sampleInput: Record<string, unknown>) => Promise<void>;
    reset: () => void;
};

export function useWorkflowTestRun(
    options: UseWorkflowTestRunOptions,
): UseWorkflowTestRunReturn {
    const wait =
        options.wait ??
        ((ms: number) =>
            new Promise<void>((resolve) => setTimeout(resolve, ms)));
    const revealStepMs = options.revealStepMs ?? DEFAULT_REVEAL_STEP_MS;

    const state = ref<TestRunState>('idle');
    const result = ref<ExecutionResult | null>(null);
    const statuses = ref<Record<string, NodeRunStatus>>({});
    const flowingEdgeIds = ref<Set<string>>(new Set());

    const resultsByKey = computed(() => {
        const byKey = new Map<string, NodeRunResult>();
        for (const node of result.value?.nodes ?? []) {
            byKey.set(node.nodeKey, node);
        }
        return byKey;
    });

    function resetStatuses(): void {
        statuses.value = Object.fromEntries(
            options.getNodes().map((node) => [node.key, 'idle' as const]),
        );
    }

    function reset(): void {
        state.value = 'idle';
        result.value = null;
        flowingEdgeIds.value = new Set();
        resetStatuses();
    }

    function setNodeStatus(key: string, status: NodeRunStatus): void {
        statuses.value = { ...statuses.value, [key]: status };
    }

    /**
     * Arêtes sortantes d'un node réussi : ne coulent que celles qui mènent à
     * un node exécuté — l'arête de la branche non prise (cible `skipped`)
     * reste au repos. Déduit de la réponse seule, sans id de type en dur.
     */
    function flowOutgoingEdges(sourceKey: string): void {
        const executed = new Set(
            (result.value?.nodes ?? [])
                .filter((node) => node.status !== 'skipped')
                .map((node) => node.nodeKey),
        );
        const flowing = new Set(flowingEdgeIds.value);
        for (const edge of options.getEdges()) {
            if (
                edge.sourceNodeKey === sourceKey &&
                executed.has(edge.targetNodeKey)
            ) {
                flowing.add(edge.id);
                setTimeout(() => {
                    const next = new Set(flowingEdgeIds.value);
                    next.delete(edge.id);
                    flowingEdgeIds.value = next;
                }, EDGE_FLOW_MS);
            }
        }
        flowingEdgeIds.value = flowing;
    }

    async function start(sampleInput: Record<string, unknown>): Promise<void> {
        if (state.value === 'running') {
            return;
        }

        state.value = 'running';
        result.value = null;
        flowingEdgeIds.value = new Set();
        resetStatuses();

        let run: ExecutionResult;
        try {
            run = await options.postTestRun(sampleInput);
        } catch (error) {
            options.onRequestError?.(toErrorMessages(error));
            state.value = 'idle';
            return;
        }

        result.value = run;
        options.onRunStarted?.(run);

        const stepMs = Math.min(
            revealStepMs,
            Math.floor(REVEAL_CAP_MS / Math.max(1, run.nodes.length)),
        );

        for (const node of run.nodes) {
            if (node.status === 'skipped') {
                setNodeStatus(node.nodeKey, 'skipped');
                continue;
            }
            setNodeStatus(node.nodeKey, 'running');
            await wait(stepMs);
            setNodeStatus(node.nodeKey, node.status);
            if (node.status === 'ok') {
                flowOutgoingEdges(node.nodeKey);
            }
        }

        state.value = run.status === 'completed' ? 'completed' : 'failed';
    }

    return {
        state,
        result,
        statuses,
        flowingEdgeIds,
        resultsByKey,
        start,
        reset,
    };
}
