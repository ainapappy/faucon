import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    RequestFailure,
    useWorkflowSaver,
    type BeforeUnloadTarget,
} from '@/composables/useWorkflowSaver';
import type { WorkflowGraphPayload } from '@/types';

const payload: WorkflowGraphPayload = {
    nodes: [
        {
            key: 'n1',
            type: 'trigger.manual',
            name: 'Départ',
            config: {},
            positionX: 60,
            positionY: 200,
        },
    ],
    edges: [],
};

/**
 * Harnais modélisant le couple builder/saver : `isDirty` compare l'état
 * courant à la baseline (dernier payload persisté), exactement comme
 * `useWorkflowBuilder`. Permet de simuler un changement pendant le PUT.
 */
function createHarness() {
    let baseline: WorkflowGraphPayload = structuredClone(payload);
    let current: WorkflowGraphPayload = structuredClone(payload);

    const putCalls: WorkflowGraphPayload[] = [];
    const errorGroups: string[][] = [];
    const resolvers: Array<() => void> = [];

    const putGraph = vi.fn((snapshot: WorkflowGraphPayload) => {
        putCalls.push(structuredClone(snapshot));
        return new Promise<void>((resolve) => {
            resolvers.push(resolve);
        });
    });

    const saver = useWorkflowSaver({
        getPayload: () => structuredClone(current),
        isDirty: () => JSON.stringify(current) !== JSON.stringify(baseline),
        markSynced: (snapshot) => {
            baseline = structuredClone(snapshot);
        },
        putGraph,
        onError: (messages) => errorGroups.push(messages),
        debounceMs: 800,
        guardTarget: null,
    });

    return {
        saver,
        putCalls,
        errorGroups,
        putGraph,
        editDuringFlight: () => {
            current = structuredClone(current);
            current.nodes.push({
                key: 'n2',
                type: 'action.email',
                name: 'Email',
                config: {},
                positionX: 300,
                positionY: 200,
            });
        },
        resolvePending: () => {
            while (resolvers.length > 0) {
                resolvers.shift()?.();
            }
        },
    };
}

describe('useWorkflowSaver', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('débounce plusieurs changements en une seule requête', async () => {
        const harness = createHarness();
        const { saver } = harness;

        expect(saver.state.value).toBe('idle');

        saver.scheduleSave();
        await vi.advanceTimersByTimeAsync(300);
        saver.scheduleSave(); // re-planifié dans la fenêtre de debounce
        await vi.advanceTimersByTimeAsync(700);

        // 300 + 700 = 1000 ms : le second schedule a repositionné l'échéance à 300 + 800.
        expect(harness.putGraph).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(100);

        expect(harness.putGraph).toHaveBeenCalledTimes(1);
        harness.resolvePending();
        await vi.waitFor(() => expect(saver.state.value).toBe('saved'));

        expect(saver.savedAt.value).toMatch(/^\d{2}:\d{2}$/);
        expect(saver.errors.value).toEqual([]);
    });

    it("passe par l'état saving pendant la requête", async () => {
        const harness = createHarness();

        harness.saver.scheduleSave();
        await vi.advanceTimersByTimeAsync(800);

        expect(harness.saver.state.value).toBe('saving');
        harness.resolvePending();
        await vi.waitFor(() => expect(harness.saver.state.value).toBe('saved'));
    });

    it('bascule en erreur avec les messages 422 agrégés puis se ré-arme', async () => {
        const harness = createHarness();

        // Premier PUT : rejet 422 (cycle).
        harness.putGraph.mockImplementationOnce(() =>
            Promise.reject(
                new RequestFailure(['The graph contains a cycle.'], '422'),
            ),
        );
        harness.saver.scheduleSave();
        await vi.advanceTimersByTimeAsync(800);
        await vi.waitFor(() => expect(harness.saver.state.value).toBe('error'));

        expect(harness.errorGroups).toEqual([['The graph contains a cycle.']]);
        expect(harness.saver.errors.value).toEqual([
            'The graph contains a cycle.',
        ]);

        // Ré-armement : un nouveau changement re-lance une sauvegarde propre.
        harness.saver.scheduleSave();
        await vi.advanceTimersByTimeAsync(800);
        harness.resolvePending();
        await vi.waitFor(() => expect(harness.saver.state.value).toBe('saved'));
    });

    it('re-planifie une sauvegarde si un changement survient pendant le PUT', async () => {
        const harness = createHarness();

        harness.saver.scheduleSave();
        await vi.advanceTimersByTimeAsync(800);

        // Le PUT est en vol : un changement arrive pendant ce temps.
        expect(harness.saver.state.value).toBe('saving');
        harness.editDuringFlight();
        harness.saver.scheduleSave(); // mis en attente, pas de second PUT immédiat

        harness.resolvePending();
        await vi.waitFor(() => expect(harness.saver.state.value).toBe('saved'));

        // Le premier PUT est marqué synchronisé sur SON snapshot : le graphe reste
        // dirty et la re-planification démarre après la fin du PUT (800 ms).
        await vi.advanceTimersByTimeAsync(800);
        expect(harness.putCalls.length).toBeGreaterThanOrEqual(2);
    });

    it('flush envoie immédiatement les changements en attente', async () => {
        const harness = createHarness();

        harness.editDuringFlight(); // le graphe devient dirty
        harness.saver.scheduleSave();
        const flushed = harness.saver.flush();

        expect(harness.putGraph).toHaveBeenCalledTimes(1);
        harness.resolvePending();
        await expect(flushed).resolves.toBe(true);
    });

    it('flush ne renvoie rien si le graphe est propre', async () => {
        const harness = createHarness();
        // État initial : current === baseline, rien à enregistrer.

        const flushed = harness.saver.flush();
        await expect(flushed).resolves.toBe(true);
        expect(harness.putGraph).not.toHaveBeenCalled();
    });

    it('enregistre un garde beforeunload qui bloque quand le graphe est dirty', () => {
        const handlers: Array<(event: BeforeUnloadEvent) => void> = [];
        const target = {
            addEventListener: (
                _type: 'beforeunload',
                handler: (event: BeforeUnloadEvent) => void,
            ) => {
                handlers.push(handler);
            },
            removeEventListener: (
                _type: 'beforeunload',
                handler: (event: BeforeUnloadEvent) => void,
            ) => {
                const index = handlers.indexOf(handler);
                if (index >= 0) {
                    handlers.splice(index, 1);
                }
            },
        } satisfies BeforeUnloadTarget;

        useWorkflowSaver({
            getPayload: () => payload,
            isDirty: () => true,
            markSynced: () => {},
            putGraph: () => Promise.resolve(),
            guardTarget: target,
        });

        expect(handlers).toHaveLength(1);

        const preventDefault = vi.fn();
        const event = {
            preventDefault,
            returnValue: '',
        } as unknown as BeforeUnloadEvent;
        handlers[0]?.(event);
        expect(preventDefault).toHaveBeenCalled();

        handlers[0]?.(new Event('beforeunload') as BeforeUnloadEvent);
    });
});
