import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    formatRunSeconds,
    parseSampleInput,
    useWorkflowTestRun,
    type UseWorkflowTestRunReturn,
} from '@/composables/useWorkflowTestRun';
import { RequestFailure } from '@/composables/useWorkflowSaver';
import type { ExecutionResult, NodeRunResult } from '@/types';

const NODES = [{ key: 'n1' }, { key: 'n2' }, { key: 'n3' }, { key: 'n4' }];

const EDGES = [
    { id: 'e1', sourceNodeKey: 'n1', targetNodeKey: 'n2', sourceHandle: null },
    { id: 'e2', sourceNodeKey: 'n2', targetNodeKey: 'n3', sourceHandle: null },
    // Arête vers la branche non prise : sa cible est `skipped`, elle ne doit jamais « couler ».
    {
        id: 'e3',
        sourceNodeKey: 'n3',
        targetNodeKey: 'n4',
        sourceHandle: 'false',
    },
];

function nodeRun(
    nodeKey: string,
    overrides: Partial<NodeRunResult> = {},
): NodeRunResult {
    return {
        type: 'data.transform',
        name: 'Transformation',
        status: 'ok',
        durationMs: 3,
        output: { value: 'x' },
        error: null,
        nodeKey,
        ...overrides,
    };
}

const completedResult: ExecutionResult = {
    status: 'completed',
    durationMs: 12,
    nodes: [
        nodeRun('n1', {
            type: 'trigger.manual',
            name: 'Manuel',
            output: { email: 'client@example.com' },
        }),
        nodeRun('n2'),
        nodeRun('n3', {
            type: 'logic.condition',
            name: 'Condition',
            output: { branch: 'false', value: false },
        }),
        nodeRun('n4', {
            type: 'data.output',
            name: 'Sortie',
            status: 'skipped',
            durationMs: 0,
            output: {},
        }),
    ],
    errors: [],
};

const failedResult: ExecutionResult = {
    status: 'failed',
    durationMs: 30,
    nodes: [
        nodeRun('n1', { type: 'trigger.manual', name: 'Manuel' }),
        nodeRun('n2', {
            status: 'error',
            durationMs: 4,
            output: {},
            error: {
                nodeKey: 'n2',
                type: 'data.transform',
                reason: 'path_not_found',
                message:
                    'Le chemin « n1.email » est introuvable dans le contexte.',
            },
        }),
        nodeRun('n3', {
            status: 'skipped',
            durationMs: 0,
            output: {},
        }),
        nodeRun('n4', {
            status: 'skipped',
            durationMs: 0,
            output: {},
        }),
    ],
    errors: [
        {
            nodeKey: 'n2',
            type: 'data.transform',
            reason: 'path_not_found',
            message: 'Le chemin « n1.email » est introuvable dans le contexte.',
        },
    ],
};

const validationFailure: ExecutionResult = {
    status: 'failed',
    durationMs: 0,
    nodes: [],
    errors: [
        {
            nodeKey: null,
            type: 'validation',
            reason: 'no_trigger',
            message:
                'Le workflow doit contenir exactement un node déclencheur.',
        },
    ],
};

/**
 * Harnais : le POST est différé (`resolvePending`), comme une vraie requête
 * HTTP dont on contrôle l'arrivée. La relecture animée utilise les timers
 * réels du composable — simulés par Vitest.
 */
function createHarness(result?: ExecutionResult, error?: unknown) {
    const resolvers: Array<() => void> = [];

    const postTestRun = vi.fn(
        () =>
            new Promise<ExecutionResult>((resolve, reject) => {
                resolvers.push(() => {
                    if (error !== undefined) {
                        reject(error);
                        return;
                    }
                    resolve(structuredClone(result ?? completedResult));
                });
            }),
    );

    const errorGroups: string[][] = [];
    const runStartedResults: ExecutionResult[] = [];

    const testRun: UseWorkflowTestRunReturn = useWorkflowTestRun({
        postTestRun,
        getNodes: () => NODES,
        getEdges: () => EDGES,
        onRequestError: (messages) => errorGroups.push(messages),
        onRunStarted: (run) => runStartedResults.push(run),
        revealStepMs: 100,
    });

    return {
        testRun,
        postTestRun,
        errorGroups,
        runStartedResults,
        resolvePending: () => {
            while (resolvers.length > 0) {
                resolvers.shift()?.();
            }
        },
    };
}

describe('useWorkflowTestRun', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('enchaîne idle → running → completed en révélant les nodes dans l’ordre d’exécution', async () => {
        const harness = createHarness();

        const pending = harness.testRun.start({
            email: 'client@example.com',
        });
        expect(harness.testRun.state.value).toBe('running');
        expect(harness.postTestRun).toHaveBeenCalledWith({
            email: 'client@example.com',
        });
        expect(harness.testRun.statuses.value).toEqual({
            n1: 'idle',
            n2: 'idle',
            n3: 'idle',
            n4: 'idle',
        });

        harness.resolvePending();
        await vi.advanceTimersByTimeAsync(100);
        expect(harness.testRun.statuses.value.n1).toBe('ok');
        expect(harness.testRun.statuses.value.n2).toBe('running');

        await vi.advanceTimersByTimeAsync(100);
        expect(harness.testRun.statuses.value.n2).toBe('ok');
        expect(harness.testRun.statuses.value.n3).toBe('running');

        await vi.advanceTimersByTimeAsync(100);
        expect(harness.testRun.statuses.value.n3).toBe('ok');
        expect(harness.testRun.statuses.value.n4).toBe('skipped');

        await pending;
        expect(harness.testRun.state.value).toBe('completed');
        expect(harness.testRun.result.value?.status).toBe('completed');
    });

    it('anime les arêtes du chemin parcouru, jamais celles de la branche non prise, puis les rend au repos', async () => {
        const harness = createHarness();

        const pending = harness.testRun.start({});
        harness.resolvePending();

        await vi.advanceTimersByTimeAsync(100);
        expect(harness.testRun.flowingEdgeIds.value.has('e1')).toBe(true);
        expect(harness.testRun.flowingEdgeIds.value.has('e3')).toBe(false);

        await vi.advanceTimersByTimeAsync(1400);
        expect(harness.testRun.flowingEdgeIds.value.has('e1')).toBe(false);

        await pending;
    });

    it('propage l’échec d’un node : state failed, node en erreur, suivants skipped', async () => {
        const harness = createHarness(failedResult);

        const pending = harness.testRun.start({});
        harness.resolvePending();
        await vi.runAllTimersAsync();
        await pending;

        expect(harness.testRun.state.value).toBe('failed');
        expect(harness.testRun.statuses.value).toEqual({
            n1: 'ok',
            n2: 'error',
            n3: 'skipped',
            n4: 'skipped',
        });
    });

    it('traite un échec de validation comme un résultat sans relecture (nodes vides)', async () => {
        const harness = createHarness(validationFailure);

        const pending = harness.testRun.start({});
        harness.resolvePending();
        await pending;

        expect(harness.testRun.state.value).toBe('failed');
        expect(harness.testRun.statuses.value).toEqual({
            n1: 'idle',
            n2: 'idle',
            n3: 'idle',
            n4: 'idle',
        });
        expect(harness.testRun.result.value?.errors[0]?.reason).toBe(
            'no_trigger',
        );
    });

    it('notifie onRunStarted à la réception de la réponse, avant la fin de la relecture', async () => {
        const harness = createHarness();

        const pending = harness.testRun.start({});
        harness.resolvePending();
        await vi.advanceTimersByTimeAsync(0);

        expect(harness.runStartedResults).toHaveLength(1);
        expect(harness.testRun.state.value).toBe('running');

        await vi.runAllTimersAsync();
        await pending;
    });

    it('retourne à idle avec les messages 422 agrégés et sans résultat', async () => {
        const harness = createHarness(
            undefined,
            new RequestFailure(['L’input doit être un JSON valide.'], '422'),
        );

        const pending = harness.testRun.start({});
        harness.resolvePending();
        await pending;

        expect(harness.testRun.state.value).toBe('idle');
        expect(harness.testRun.result.value).toBeNull();
        expect(harness.errorGroups).toEqual([
            ['L’input doit être un JSON valide.'],
        ]);
    });

    it('retourne à idle avec un message générique sur une erreur réseau', async () => {
        const harness = createHarness(undefined, new Error('network down'));

        const pending = harness.testRun.start({});
        harness.resolvePending();
        await pending;

        expect(harness.testRun.state.value).toBe('idle');
        expect(harness.errorGroups).toHaveLength(1);
        expect(harness.errorGroups[0]?.[0]).toContain(
            'Le test n’a pas pu être lancé',
        );
    });

    it('ignore un second start pendant qu’un run est en cours', async () => {
        const harness = createHarness();

        const first = harness.testRun.start({});
        const second = harness.testRun.start({ other: true });

        expect(harness.postTestRun).toHaveBeenCalledTimes(1);

        harness.resolvePending();
        await vi.runAllTimersAsync();
        await first;
        await second;

        expect(harness.testRun.state.value).toBe('completed');
    });

    it('réinitialise statuts et résultat lors d’une relance', async () => {
        const harness = createHarness();
        const first = harness.testRun.start({});
        harness.resolvePending();
        await vi.runAllTimersAsync();
        await first;
        expect(harness.testRun.state.value).toBe('completed');

        const second = harness.testRun.start({});
        expect(harness.testRun.state.value).toBe('running');
        expect(harness.testRun.statuses.value).toEqual({
            n1: 'idle',
            n2: 'idle',
            n3: 'idle',
            n4: 'idle',
        });
        expect(harness.testRun.result.value).toBeNull();

        harness.resolvePending();
        await vi.runAllTimersAsync();
        await second;
        expect(harness.testRun.state.value).toBe('completed');
    });

    it('remet tout à zéro via reset', async () => {
        const harness = createHarness();
        const pending = harness.testRun.start({});
        harness.resolvePending();
        await vi.runAllTimersAsync();
        await pending;

        harness.testRun.reset();

        expect(harness.testRun.state.value).toBe('idle');
        expect(harness.testRun.result.value).toBeNull();
        expect(harness.testRun.statuses.value).toEqual({
            n1: 'idle',
            n2: 'idle',
            n3: 'idle',
            n4: 'idle',
        });
        expect(harness.testRun.flowingEdgeIds.value.size).toBe(0);
        expect(harness.testRun.resultsByKey.value.size).toBe(0);
    });

    it('plafonne la durée de relecture d’un long graphe', async () => {
        const longNodes = Array.from({ length: 30 }, (_, index) => ({
            key: `k${index}`,
        }));
        const longResult: ExecutionResult = {
            status: 'completed',
            durationMs: 40,
            nodes: longNodes.map(({ key }) => nodeRun(key)),
            errors: [],
        };

        const resolvers: Array<() => void> = [];
        const testRun = useWorkflowTestRun({
            postTestRun: () =>
                new Promise<ExecutionResult>((resolve) => {
                    resolvers.push(() => resolve(longResult));
                }),
            getNodes: () => longNodes,
            getEdges: () => [],
            revealStepMs: 120,
        });

        const pending = testRun.start({});
        while (resolvers.length > 0) {
            resolvers.shift()?.();
        }
        await vi.advanceTimersByTimeAsync(1600);
        await pending;

        expect(testRun.state.value).toBe('completed');
        for (const { key } of longNodes) {
            expect(testRun.statuses.value[key]).toBe('ok');
        }
    });

    it('expose les résultats par clé de node', async () => {
        const harness = createHarness();
        const pending = harness.testRun.start({});
        harness.resolvePending();
        await vi.runAllTimersAsync();
        await pending;

        const byKey = harness.testRun.resultsByKey.value;
        expect(byKey.get('n1')?.output).toEqual({
            email: 'client@example.com',
        });
        expect(byKey.get('n3')?.output).toEqual({
            branch: 'false',
            value: false,
        });
        expect(byKey.get('n4')?.status).toBe('skipped');
    });
});

describe('parseSampleInput', () => {
    it('accepte un objet JSON', () => {
        const { sample, error } = parseSampleInput(
            '{ "email": "client@example.com" }',
        );
        expect(error).toBeNull();
        expect(sample).toEqual({ email: 'client@example.com' });
    });

    it('n’affiche aucune erreur sur un texte vide (le bouton reste désactivé)', () => {
        const { sample, error } = parseSampleInput('   ');
        expect(sample).toBeNull();
        expect(error).toBeNull();
    });

    it('signale un JSON malformé comme la maquette', () => {
        const { sample, error } = parseSampleInput('{ "email": ');
        expect(sample).toBeNull();
        expect(error).toMatch(/^JSON invalide — /);
    });

    it('refuse une liste ou un scalaire, comme le contrat 422 du backend', () => {
        for (const text of ['[1, 2]', '42', '"texte"', 'null']) {
            const { sample, error } = parseSampleInput(text);
            expect(sample).toBeNull();
            expect(error).toBe('L’input doit être un objet JSON.');
        }
    });
});

describe('formatRunSeconds', () => {
    it('formate la durée d’un run comme la maquette', () => {
        expect(formatRunSeconds(12)).toBe('0.0 s');
        expect(formatRunSeconds(1200)).toBe('1.2 s');
        expect(formatRunSeconds(9876)).toBe('9.9 s');
    });
});
