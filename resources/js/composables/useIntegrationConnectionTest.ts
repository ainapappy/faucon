/*
 * Test de connexion d'une intégration (phase 5, lot F).
 *
 * Machine d'états `idle → testing → résultat` par intégration : un seul test
 * à la fois (identifié par `testingId`), la réponse `{ok, message}` du POST
 * `integrations.test` est TOUJOURS un résultat (un échec de connexion est une
 * réponse métier, pas une erreur HTTP) — seule une erreur transport passe en
 * `onError`.
 *
 * Couche HTTP injectée : le composable reste testable hors navigateur
 * (Vitest) et la page reste un simple câblage `useHttp` + Wayfinder, toasts
 * et rechargement du badge côté page.
 */
import { ref } from 'vue';
import type { Ref } from 'vue';
import type { IntegrationSummary } from '@/types';

/** Réponse du POST `integrations.test` (TestIntegrationConnectionController). */
export type ConnectionTestResult = {
    ok: boolean;
    message: string;
};

export type UseIntegrationConnectionTestOptions = {
    /** POST `integrations.test` — câblage `useHttp` + Wayfinder de la page. */
    postTest: (
        integration: IntegrationSummary,
    ) => Promise<ConnectionTestResult>;
    /** Résultat reçu (ok ou métier-échec) — toast + rechargement du badge côté page. */
    onResult?: (
        integration: IntegrationSummary,
        result: ConnectionTestResult,
    ) => void;
    /** Erreur transport (réseau, 4xx/5xx) — toast côté page. */
    onError?: (integration: IntegrationSummary) => void;
};

export type UseIntegrationConnectionTestReturn = {
    /** Id de l’intégration en cours de test, null au repos (idle). */
    testingId: Ref<number | null>;
    /** Lance le test — ignore silencieusement si un test est déjà en vol. */
    testConnection: (integration: IntegrationSummary) => Promise<void>;
};

export function useIntegrationConnectionTest(
    options: UseIntegrationConnectionTestOptions,
): UseIntegrationConnectionTestReturn {
    const testingId: Ref<number | null> = ref(null);

    async function testConnection(
        integration: IntegrationSummary,
    ): Promise<void> {
        if (testingId.value !== null) {
            return;
        }

        testingId.value = integration.id;

        try {
            const result = await options.postTest(integration);
            options.onResult?.(integration, result);
        } catch {
            options.onError?.(integration);
        } finally {
            testingId.value = null;
        }
    }

    return { testingId, testConnection };
}
