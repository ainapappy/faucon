/*
 * Libellés FR des options de select du catalogue (couche présentation, D16).
 *
 * Le catalogue backend porte les valeurs machine (`fail`, `continue`…) ; le
 * front les habille ici, sans muter les définitions sérialisées en props.
 * Toute option sans libellé connu s'affiche brute — cohérent avec le select
 * `operator` de la Condition dont les valeurs (`==`, `!=`) SONT leur
 * représentation.
 */
const OPTION_LABELS: Record<string, string> = {
    fail: 'Échouer',
    continue: 'Continuer',
};

/** Libellé FR d'une option de select du catalogue, valeur brute en repli. */
export function nodeOptionLabel(value: string): string {
    return OPTION_LABELS[value] ?? value;
}
