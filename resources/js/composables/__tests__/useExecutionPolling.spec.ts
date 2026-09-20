import { describe, expect, it, vi, beforeEach } from 'vitest';
import { nextTick, ref } from 'vue';
import type { WorkflowExecutionStatus } from '@/types';

/*
 * Le polling repose sur `usePoll` d'Inertia — remplacé ici par un double
 * espion : ce que la spec épingle, c'est la machine d'états (démarre sur
 * un statut non final, s'arrête sur un statut final ou sans sélection).
 */
const start = vi.fn();
const stop = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePoll: vi.fn(() => ({ start, stop, polling: ref(false) })),
}));

const { useExecutionPolling } =
    await import('@/composables/useExecutionPolling');

function statusRef(value: WorkflowExecutionStatus | null) {
    return ref<WorkflowExecutionStatus | null>(value);
}

describe('useExecutionPolling', () => {
    beforeEach(() => {
        start.mockClear();
        stop.mockClear();
    });

    it('starts polling when a non-final execution is selected', () => {
        const status = statusRef('running');

        useExecutionPolling({ status });

        expect(start).toHaveBeenCalledTimes(1);
        expect(stop).not.toHaveBeenCalled();
    });

    it('stops polling when the execution reaches a final state', async () => {
        const status = statusRef('running');

        useExecutionPolling({ status });
        status.value = 'completed';
        await nextTick();

        expect(stop).toHaveBeenCalled();
    });

    it('stops polling for every final state', async () => {
        for (const final of ['completed', 'failed', 'cancelled'] as const) {
            start.mockClear();
            stop.mockClear();

            const status = statusRef('pending');
            useExecutionPolling({ status });
            status.value = final;
            await nextTick();

            expect(stop).toHaveBeenCalled();
        }
    });

    it('never polls without a selection', () => {
        const status = statusRef(null);

        useExecutionPolling({ status });

        expect(start).not.toHaveBeenCalled();
    });

    it('stops when the selection is cleared mid-run', async () => {
        const status = statusRef('pending');

        useExecutionPolling({ status });
        status.value = null;
        await nextTick();

        expect(stop).toHaveBeenCalled();
    });
});
