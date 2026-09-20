/**
 * Notifications in-app (phase 10, D5/D10) — miroir de la projection
 * `NotificationPresenter` (racine partagée + page historique).
 */

/** Clé courte et stable — le front ne dépend jamais du FQCN. */
export type NotificationType = 'execution_failed' | 'generic';

/** Payload d'une notification d'échec — sans donnée sensible (D4). */
export type ExecutionFailedNotificationData = {
    workflowId: number;
    workflowName: string;
    executionId: number;
    /** Équipe du run : permet le deep-link depuis n'importe quelle équipe. */
    teamSlug: string;
    nodeKey: string | null;
    nodeType: string | null;
    reason: string;
    message: string;
};

/**
 * Item de l'historique (prop de PAGE `notifications.data[*]` — paginator
 * aplati). `read` est dérivé côté back (`read_at !== null`) et n'existe QUE
 * dans la page : les items du `recent` de la cloche n'ont pas cette clé.
 */
export type NotificationItem = {
    /** UUID de la table notifications — string, jamais number. */
    id: string;
    type: NotificationType;
    /** null ou forme libre pour les types inconnus (rendus génériques). */
    data: ExecutionFailedNotificationData | null;
    createdAt: string;
    read: boolean;
};

/** Item du `recent` de la cloche (prop racine) — sans la clé `read`. */
export type NotificationSummaryItem = Omit<NotificationItem, 'read'>;

/** Prop racine `notifications` — null si invité. */
export type NotificationsSummary = {
    unreadCount: number;
    /** 5 non-lues maximum, plus récentes d'abord. */
    recent: NotificationSummaryItem[];
};

/** Serializeur Inertia du paginator Laravel (aplati, pas de wrapper meta). */
export type PaginatedNotifications = {
    data: NotificationItem[];
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
};
