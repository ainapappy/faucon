/*
 * Usage tokens des nodes IA (phase 6, V10) : le moteur ajoute la clé
 * `usage: {prompt_tokens, completion_tokens}` à la sortie de chaque node IA.
 *
 * Formatage prudent — toute forme inattendue renvoie null et le badge ne se
 * rend tout simplement pas (les clés supplémentaires sont tolérées, seules
 * les deux attendues doivent être des nombres finis).
 */
export function formatAiUsage(
    output: Record<string, unknown> | null | undefined,
): string | null {
    const usage = output?.usage;
    if (typeof usage !== 'object' || usage === null || Array.isArray(usage)) {
        return null;
    }

    const record = usage as Record<string, unknown>;
    const { prompt_tokens: promptTokens, completion_tokens: completionTokens } =
        record;

    if (
        typeof promptTokens !== 'number' ||
        typeof completionTokens !== 'number' ||
        !Number.isFinite(promptTokens) ||
        !Number.isFinite(completionTokens)
    ) {
        return null;
    }

    return `Tokens : ${promptTokens} prompt · ${completionTokens} réponse`;
}
