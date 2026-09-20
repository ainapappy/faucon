import { usePoll } from '@inertiajs/vue3';
import { computed, watch, type Ref } from 'vue';
import type { WorkflowExecutionStatus } from '@/types';

/*
 * Polling de l'exécution sélectionnée (phase 7, maquette executions.html :
 * « Cette vue se met à jour automatiquement »).
 *
 * Reload partiel `only: ['execution']` toutes les 1,5 s tant que l'état
 * n'est pas final ; arrêt automatique sur completed/failed/cancelled.
 * Le throttle 90 % en onglet arrière-plan est le comportement par défaut
 * d'Inertia v3 (voulu).
 */
export type UseExecutionPollingOptions = {
    /** Statut de l'exécution sélectionnée, `null` si aucune. */
    status: Ref<WorkflowExecutionStatus | null>;
};

export function useExecutionPolling(
    options: UseExecutionPollingOptions,
    intervalMs = 1500,
) {
    const isFinal = computed(
        () =>
            options.status.value === 'completed' ||
            options.status.value === 'failed' ||
            options.status.value === 'cancelled',
    );

    const poll = usePoll(
        intervalMs,
        () => ({
            only: ['execution'],
        }),
        { autoStart: false },
    );

    // Démarre à l'ouverture d'une exécution non finale, s'arrête à l'état
    // final (et redémarre si une autre exécution non finale est ouverte).
    watch(
        [options.status, isFinal],
        () => {
            if (options.status.value === null || isFinal.value) {
                poll.stop();

                return;
            }

            poll.start();
        },
        { immediate: true },
    );

    return { isFinal, polling: poll.polling, stop: poll.stop };
}
