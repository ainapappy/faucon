export type IntegrationType = 'generic_http' | 'smtp';

/**
 * Résumé d'une intégration servi par la page settings/Integrations.
 *
 * Forme VOLONTAIREMENT sans credentials : le backend ne les renvoie jamais
 * (chiffrés au repos, ils ne transitent ni dans les props ni dans un état
 * front). Aucun champ de cette forme ne doit pré-remplir un secret.
 */
export type IntegrationSummary = {
    id: number;
    name: string;
    type: IntegrationType;
    /** Méta non secrète affichable : baseUrl (generic_http) ou host:port (smtp) — jamais un secret. */
    meta: string | null;
    /** ISO — date du dernier test de connexion. */
    lastTestedAt: string | null;
    /** Résultat du dernier test de connexion (null = jamais testée). */
    lastTestSucceeded: boolean | null;
    /** Nombre de workflows référençant l'intégration (nodes action.http / action.email). */
    usedByWorkflows: number;
};
