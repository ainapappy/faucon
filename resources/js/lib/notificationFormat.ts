/**
 * Présentation pure des notifications in-app (phase 10, D9/D10).
 *
 * Le PIÈGE épinglé côté back : sur la page `notifications/Index`, la prop de
 * PAGE `notifications` (paginator aplati) ÉCRASE la prop racine du même nom
 * (résumé {unreadCount, recent}). `readNotificationsSummary` lit la prop
 * partagée en vérifiant sa FORME — sur la page historique elle rend null au
 * lieu de livrer un paginator à la cloche.
 */
import type {
    NotificationItem,
    NotificationSummaryItem,
    NotificationsSummary,
} from '@/types';

/** Vrai quand l'objet porte la forme du résumé racine (et pas du paginator). */
export function isNotificationsSummary(
    value: unknown,
): value is NotificationsSummary {
    return (
        typeof value === 'object' &&
        value !== null &&
        'unreadCount' in value &&
        'recent' in value
    );
}

/**
 * Prop racine `notifications` lue défensivement — null pour un invité ET sur
 * la page historique (où la prop est écrasée par le paginator).
 */
export function readNotificationsSummary(
    shared: unknown,
): NotificationsSummary | null {
    return isNotificationsSummary(shared) ? shared : null;
}

/** Titre d'un item (« “{workflow}” a échoué » / « Notification »). */
export function notificationTitle(
    item: NotificationItem | NotificationSummaryItem,
): string {
    if (item.type === 'execution_failed' && item.data) {
        return `« ${item.data.workflowName} » a échoué`;
    }

    return 'Notification';
}

/** Détail d'un item : raison + message tronqué par le back (D4). */
export function notificationDetail(
    item: NotificationItem | NotificationSummaryItem,
): string | null {
    if (item.type !== 'execution_failed' || !item.data) {
        return null;
    }

    const message = item.data.message.trim();

    if (message === '') {
        return `Raison : ${item.data.reason}`;
    }

    return message;
}
