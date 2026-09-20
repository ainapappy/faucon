import { describe, expect, it, vi } from 'vitest';
import {
    useIntegrationConnectionTest,
    type ConnectionTestResult,
} from '@/composables/useIntegrationConnectionTest';
import type { IntegrationSummary } from '@/types';

function summary(id = 1): IntegrationSummary {
    return {
        id,
        name: 'API interne',
        type: 'generic_http',
        meta: null,
        lastTestedAt: null,
        lastTestSucceeded: null,
        usedByWorkflows: 0,
    };
}

describe('useIntegrationConnectionTest', () => {
    it('reste idle avant tout test (testingId null)', () => {
        const { testingId } = useIntegrationConnectionTest({
            postTest: vi.fn(),
        });

        expect(testingId.value).toBeNull();
    });

    it('passe testing pendant le vol puis revient au repos, et notifie le résultat', async () => {
        let releaseInFlight = (): void => {};
        const postTest = vi
            .fn<(i: IntegrationSummary) => Promise<ConnectionTestResult>>()
            .mockImplementation(
                () =>
                    new Promise((resolve) => {
                        releaseInFlight = () =>
                            resolve({
                                ok: true,
                                message: 'Connexion SMTP établie.',
                            });
                    }),
            );
        const onResult = vi.fn();
        const onError = vi.fn();

        const { testingId, testConnection } = useIntegrationConnectionTest({
            postTest,
            onResult,
            onError,
        });

        const pending = testConnection(summary(7));

        expect(testingId.value).toBe(7);

        releaseInFlight();
        await pending;

        expect(testingId.value).toBeNull();
        expect(onResult).toHaveBeenCalledTimes(1);
        expect(onResult).toHaveBeenCalledWith(summary(7), {
            ok: true,
            message: 'Connexion SMTP établie.',
        });
        expect(onError).not.toHaveBeenCalled();
    });

    it('transmet un échec métier (ok: false) comme un résultat, pas une erreur', async () => {
        const onResult = vi.fn();
        const onError = vi.fn();

        const { testConnection } = useIntegrationConnectionTest({
            postTest: async () => ({
                ok: false,
                message:
                    'Connexion établie mais authentification refusée (statut 401).',
            }),
            onResult,
            onError,
        });

        await testConnection(summary());

        expect(onResult).toHaveBeenCalledTimes(1);
        expect(onResult).toHaveBeenCalledWith(summary(), {
            ok: false,
            message:
                'Connexion établie mais authentification refusée (statut 401).',
        });
        expect(onError).not.toHaveBeenCalled();
    });

    it('route les erreurs transport vers onError', async () => {
        const onResult = vi.fn();
        const onError = vi.fn();

        const { testingId, testConnection } = useIntegrationConnectionTest({
            postTest: async () => {
                throw new Error('network');
            },
            onResult,
            onError,
        });

        await testConnection(summary(3));

        expect(testingId.value).toBeNull();
        expect(onError).toHaveBeenCalledTimes(1);
        expect(onError).toHaveBeenCalledWith(summary(3));
        expect(onResult).not.toHaveBeenCalled();
    });

    it('ignore un second appel tant qu’un test est en vol', async () => {
        const postTest = vi
            .fn<(i: IntegrationSummary) => Promise<ConnectionTestResult>>()
            .mockImplementation(
                () => new Promise((resolve) => setTimeout(resolve, 0)),
            );
        const onResult = vi.fn();

        const { testingId, testConnection } = useIntegrationConnectionTest({
            postTest,
            onResult,
        });

        const first = testConnection(summary(1));
        const second = testConnection(summary(2));

        await Promise.all([first, second]);

        expect(postTest).toHaveBeenCalledTimes(1);
        expect(postTest).toHaveBeenCalledWith(summary(1));
        expect(onResult).toHaveBeenCalledTimes(1);
        expect(testingId.value).toBeNull();
    });

    it('autorise une relance après la fin du test précédent', async () => {
        const postTest = vi
            .fn<(i: IntegrationSummary) => Promise<ConnectionTestResult>>()
            .mockResolvedValue({ ok: true, message: 'Connexion établie.' });

        const { testConnection } = useIntegrationConnectionTest({ postTest });

        await testConnection(summary(1));
        await testConnection(summary(2));

        expect(postTest).toHaveBeenCalledTimes(2);
    });
});
