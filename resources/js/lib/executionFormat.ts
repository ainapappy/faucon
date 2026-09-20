/**
 * Formatage des exécutions persistées (phase 7) — miroir du `$fmt.ms`
 * de la maquette executions.html et des dates de l'historique.
 */

/**
 * Durée lisible : `860 ms`, `4,1 s`, `2 min 05 s`. `null` ou 0 → `—`
 * (0 ms est une durée valide uniquement pour un node skipped : la liste
 * affiche `—` tant que l'exécution n'a pas de durée mesurée).
 */
export function formatDurationMs(ms: number | null | undefined): string {
    if (ms === null || ms === undefined || ms < 0) {
        return '—';
    }

    if (ms < 1000) {
        return `${Math.round(ms)} ms`;
    }

    if (ms < 60_000) {
        const seconds = ms / 1000;

        return `${seconds.toFixed(1).replace('.', ',')} s`;
    }

    const minutes = Math.floor(ms / 60_000);
    const seconds = Math.floor((ms % 60_000) / 1000);

    return `${minutes} min ${String(seconds).padStart(2, '0')} s`;
}

/**
 * Date courte localisée : « Aujourd'hui · 12:04 » pour le jour courant,
 * sinon « 18 sept. · 11:47 ».
 */
export function formatExecutionDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    const now = new Date();
    const time = date.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });

    const sameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate();

    if (sameDay) {
        return `Aujourd’hui · ${time}`;
    }

    const day = date.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
    });

    return `${day} · ${time}`;
}

/** Libellé du déclencheur (miroir du catalogue : Manuel · Webhook · Planifié). */
export function formatTrigger(
    trigger: 'manual' | 'webhook' | 'schedule',
): string {
    return { manual: 'Manuel', webhook: 'Webhook', schedule: 'Planifié' }[
        trigger
    ];
}
