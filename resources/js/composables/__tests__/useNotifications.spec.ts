import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useNotifications } from '@/composables/useNotifications';
import type { NotificationItem, NotificationSummaryItem } from '@/types';

type VisitCall = { url: string; options?: Record<string, unknown> };

const visit = vi.fn();

const FAILED: NotificationItem = {
    id: 'uuid-1',
    type: 'execution_failed',
    data: {
        workflowId: 7,
        workflowName: 'Traitement des leads',
        executionId: 42,
        teamSlug: 'studio',
        nodeKey: null,
        nodeType: null,
        reason: 'provider_error',
        message: 'Quota dépassé.',
    },
    createdAt: '2026-09-20T10:00:00Z',
    read: false,
};

const GENERIC: NotificationSummaryItem = {
    id: 'uuid-2',
    type: 'generic',
    data: null,
    createdAt: '2026-09-20T09:00:00Z',
};

function calls(): VisitCall[] {
    return visit.mock.calls.map(([url, options]) => ({
        url: String(url),
        options: options as VisitCall['options'],
    }));
}

describe('markReadAndNavigate', () => {
    beforeEach(() => {
        visit.mockClear();
    });

    it("PATCH mark-read d'abord (preserveScroll), PUIS navigation deep-link", () => {
        const { markReadAndNavigate } = useNotifications(
            visit as unknown as typeof import('@inertiajs/vue3').router.visit,
        );

        markReadAndNavigate(FAILED);

        const [mark] = calls();

        expect(mark!.url).toBe('/notifications/uuid-1');
        expect(mark!.options).toMatchObject({
            method: 'patch',
            preserveScroll: true,
        });

        // La navigation part dans onFinish — simulée par l'appel du callback.
        const markOptions = mark!.options as Record<string, unknown>;
        (markOptions.onFinish as () => void)();

        const [, navigation] = calls();
        expect(navigation!.url).toBe(
            '/studio/workflow-executions?execution=42',
        );
    });

    it('notification générique : mark-read sans navigation', () => {
        const { markReadAndNavigate } = useNotifications(
            visit as unknown as typeof import('@inertiajs/vue3').router.visit,
        );

        markReadAndNavigate(GENERIC);

        expect(calls()).toHaveLength(1);
    });
});

describe('navigateToTarget', () => {
    beforeEach(() => {
        visit.mockClear();
    });

    it('navigue dans la BONNE équipe (payload teamSlug), même depuis une autre équipe courante', () => {
        const { navigateToTarget } = useNotifications(
            visit as unknown as typeof import('@inertiajs/vue3').router.visit,
        );

        navigateToTarget(FAILED);

        expect(calls()[0]!.url).toBe(
            '/studio/workflow-executions?execution=42',
        );
    });

    it('ne navigue pas pour un type inconnu', () => {
        const { navigateToTarget } = useNotifications(
            visit as unknown as typeof import('@inertiajs/vue3').router.visit,
        );

        navigateToTarget(GENERIC);

        expect(visit).not.toHaveBeenCalled();
    });
});

describe('markAllRead', () => {
    beforeEach(() => {
        visit.mockClear();
    });

    it('POST read-all avec preserveScroll, processing levé en fin', () => {
        const { markAllRead, markAllProcessing } = useNotifications(
            visit as unknown as typeof import('@inertiajs/vue3').router.visit,
        );

        expect(markAllProcessing.value).toBe(false);

        let finished = false;
        markAllRead(() => {
            finished = true;
        });

        expect(markAllProcessing.value).toBe(true);

        const [call] = calls();
        expect(call!.url).toBe('/notifications/read-all');
        expect(call!.options).toMatchObject({
            method: 'post',
            preserveScroll: true,
        });

        const callOptions = call!.options as Record<string, unknown>;
        (callOptions.onFinish as () => void)();
        expect(markAllProcessing.value).toBe(false);
        expect(finished).toBe(true);
    });
});
