/*
 * Libellés FR des options de select du catalogue (couche présentation, D16).
 *
 * Le catalogue backend porte les valeurs machine (`fail`, `continue`…) ; le
 * front les habille ici, sans muter les définitions sérialisées en props.
 * Toute option sans libellé connu s'affiche brute — cohérent avec le select
 * `operator` de la Condition dont les valeurs (`==`, `!=`) SONT leur
 * représentation.
 *
 * Phase 6 : les options du select Modèle des nodes IA sont composites
 * `{provider}/{model}` (V6) — affichées « model · Fournisseur », fournisseurs
 * connus seulement ; toute autre valeur reste brute (repli muet).
 */
const OPTION_LABELS: Record<string, string> = {
    fail: 'Échouer',
    continue: 'Continuer',
};

/** Fournisseurs IA connus de la config serveur — des ids, jamais de clé. */
const PROVIDER_LABELS: Record<string, string> = {
    fake: 'Démo',
    openai: 'OpenAI',
    anthropic: 'Anthropic',
};

const PROVIDER_SEPARATOR = '/';

/** Libellé FR d'une option de select du catalogue, valeur brute en repli. */
export function nodeOptionLabel(value: string): string {
    const separatorIndex = value.indexOf(PROVIDER_SEPARATOR);
    if (separatorIndex > 0) {
        const provider = PROVIDER_LABELS[value.slice(0, separatorIndex)];
        const model = value.slice(separatorIndex + PROVIDER_SEPARATOR.length);
        if (provider !== undefined && model !== '') {
            return `${model} · ${provider}`;
        }
    }
    return OPTION_LABELS[value] ?? value;
}
