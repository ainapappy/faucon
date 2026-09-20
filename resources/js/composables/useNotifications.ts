import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { read, readAll } from '@/routes/notifications';
import { index as executionsIndex } from '@/routes/workflow-executions';
import type {
    ExecutionFailedNotificationData,
    NotificationItem,
    NotificationSummaryItem,
} from '@/types';

/**
 * Actions des notifications (phase 10, D9/D10) — partagées par la cloche et
 * la page historique : mark-read individuel idempotent suivi de la navigation
 * deep-link, et « Tout marquer lu ». Couche router injectable pour les tests.
 */
export function useNotifications(
    visit: typeof router.visit = (url, options) => router.visit(url, options),
) {
    const markAllProcessing = ref(false);

    /** Exécution visée par l'item — null pour un type inconnu (générique). */
    function executionTarget(
        item: NotificationItem | NotificationSummaryItem,
    ): ExecutionFailedNotificationData | null {
        return item.type === 'execution_failed' ? item.data : null;
    }

    /**
     * Navigation deep-link vers l'exécution de l'item — dans la BONNE équipe
     * (`data.teamSlug`, l'utilisateur peut lire depuis n'importe quelle
     * équipe, A7) avec `?execution={id}` (contrat de la page Exécutions).
     */
    function navigateToTarget(
        item: NotificationItem | NotificationSummaryItem,
    ): void {
        const target = executionTarget(item);

        if (target) {
            visit(
                executionsIndex(
                    { current_team: target.teamSlug },
                    { query: { execution: target.executionId } },
                ).url,
            );
        }
    }

    /**
     * Marque l'item lue (PATCH idempotent, preserveScroll) PUIS navigue vers
     * l'exécution dans la BONNE équipe — la prop racine se recharge avec la
     * réponse et le badge baisse. La navigation reste prioritaire : même si
     * le mark-read échoue, l'utilisateur arrive sur l'exécution.
     */
    function markReadAndNavigate(
        item: NotificationItem | NotificationSummaryItem,
    ): void {
        visit(read({ notification: item.id }).url, {
            method: 'patch',
            data: {},
            preserveScroll: true,
            onFinish: () => navigateToTarget(item),
        });
    }

    /**
     * « Tout marquer lu » (POST, preserveScroll) — la prop racine réévaluée
     * avec la réponse vide le `recent` et le badge.
     */
    function markAllRead(onDone?: () => void): void {
        markAllProcessing.value = true;

        visit(readAll().url, {
            method: 'post',
            data: {},
            preserveScroll: true,
            onFinish: () => {
                markAllProcessing.value = false;
                onDone?.();
            },
        });
    }

    return {
        markAllProcessing,
        markReadAndNavigate,
        navigateToTarget,
        markAllRead,
    };
}
