import { describe, expect, it } from 'vitest';
import {
    notificationDetail,
    notificationTitle,
    readNotificationsSummary,
} from '@/lib/notificationFormat';
import type {
    NotificationItem,
    NotificationsSummary,
    PaginatedNotifications,
} from '@/types';

const FAILED_ITEM: NotificationItem = {
    id: 'uuid-1',
    type: 'execution_failed',
    data: {
        workflowId: 7,
        workflowName: 'Traitement des leads',
        executionId: 42,
        teamSlug: 'studio',
        nodeKey: 'ai_1',
        nodeType: 'ai.classification',
        reason: 'provider_error',
        message: 'Quota dépassé.',
    },
    createdAt: '2026-09-20T10:00:00Z',
    read: false,
};

const SUMMARY: NotificationsSummary = {
    unreadCount: 2,
    recent: [
        {
            id: 'uuid-1',
            type: 'execution_failed',
            data: FAILED_ITEM.data,
            createdAt: FAILED_ITEM.createdAt,
        },
    ],
};

describe('readNotificationsSummary', () => {
    it('passe le résumé racine tel quel', () => {
        expect(readNotificationsSummary(SUMMARY)).toBe(SUMMARY);
    });

    it('rend null pour un invité', () => {
        expect(readNotificationsSummary(null)).toBeNull();
    });

    it('PIÈGE paginator : la prop de page écrase la racine → null (pas de crash cloche)', () => {
        const paginator: PaginatedNotifications = {
            data: [FAILED_ITEM],
            current_page: 1,
            per_page: 15,
            last_page: 1,
            total: 1,
        };

        expect(readNotificationsSummary(paginator)).toBeNull();
    });
});

describe('libellés des items', () => {
    it("titre d'échec avec le nom du workflow (D9)", () => {
        expect(notificationTitle(FAILED_ITEM)).toBe(
            '« Traitement des leads » a échoué',
        );
    });

    it('détail : message, repli sur la raison, null en générique', () => {
        expect(notificationDetail(FAILED_ITEM)).toBe('Quota dépassé.');
        expect(
            notificationDetail({
                ...FAILED_ITEM,
                data: { ...FAILED_ITEM.data!, message: '  ' },
            }),
        ).toBe('Raison : provider_error');
        expect(
            notificationDetail({ ...FAILED_ITEM, type: 'generic', data: null }),
        ).toBeNull();
    });

    it('type générique : titre neutre', () => {
        expect(notificationTitle({ ...FAILED_ITEM, type: 'generic' })).toBe(
            'Notification',
        );
    });
});
