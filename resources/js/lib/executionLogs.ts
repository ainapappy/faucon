/**
 * Construction de la timeline et du journal de la sheet d'exécution
 * (phase 8, maquette executions.html) depuis la prop `execution.logs`.
 *
 * Le masquage des secrets et la troncature des payloads sont faits côté back
 * (`SecretRedactor`, à l'écriture) : ces helpers ne transforment jamais
 * input/output/error — le front affiche tel quel.
 */
import { formatDurationMs } from '@/lib/executionFormat';
import type {
    WorkflowExecutionDetail,
    WorkflowExecutionLogLevel,
    WorkflowExecutionLogEntry,
    WorkflowExecutionNodeLogStatus,
} from '@/types';

/** Ligne horodatée du journal, prête au rendu. */
export type ExecutionJournalLine = {
    kind: 'line';
    /** id de la row source (clé de rendu stable pendant le polling). */
    logId: number;
    attempt: number;
    /** Offset depuis le début de la tentative, formaté (« 860 ms », « 0,9 s »). */
    t: string;
    message: string;
    level: WorkflowExecutionLogLevel;
};

/** Séparateur de tentative dans le journal (« Tentative N »). */
export type ExecutionJournalSeparator = {
    kind: 'separator';
    attempt: number;
};

export type ExecutionJournalItem =
    | ExecutionJournalLine
    | ExecutionJournalSeparator;

/**
 * Une row de node de la timeline — même forme que `WorkflowExecutionLogEntry`
 * avec les champs garantis non null sur les rows `kind: 'node'` (contrat back :
 * nodeKey/type/name/status toujours présents, `durationMs` seul reste nullable
 * pour queued/skipped).
 */
export type ExecutionTimelineNode = Omit<
    WorkflowExecutionLogEntry,
    'kind' | 'nodeKey' | 'nodeType' | 'nodeName' | 'status'
> & {
    kind: 'node';
    nodeKey: string;
    nodeType: string;
    nodeName: string;
    status: WorkflowExecutionNodeLogStatus;
};

/**
 * Parcours par node : rows de node de la tentative COURANTE de l'exécution
 * (un retry ré-exécute tout le graphe — seules les rows de la dernière
 * tentative décrivent l'état présent). L'ordre d'écriture (id asc) est
 * conservé : rows queued pré-insérées, puis ok/error au fil du run.
 */
export function buildTimeline(
    execution: WorkflowExecutionDetail,
): ExecutionTimelineNode[] {
    return execution.logs.filter(
        (log): log is ExecutionTimelineNode =>
            log.kind === 'node' && log.attempt === execution.attempt,
    );
}

/**
 * Journal complet : toutes les rows portant un message (événements de cycle
 * de vie + nodes exécutés ; les rows queued/skipped n'en ont pas), avec `t`
 * relatif au début de CHAQUE tentative. Un séparateur « Tentative N »
 * précède chaque changement de tentative, seulement quand l'exécution en a
 * compté plusieurs.
 */
export function buildJournal(
    execution: WorkflowExecutionDetail,
): ExecutionJournalItem[] {
    const items: ExecutionJournalItem[] = [];
    let lastAttempt: number | null = null;

    for (const log of execution.logs) {
        if (log.message === null) {
            continue;
        }

        if (log.attempt !== lastAttempt) {
            if (execution.attempt > 1) {
                items.push({ kind: 'separator', attempt: log.attempt });
            }

            lastAttempt = log.attempt;
        }

        items.push({
            kind: 'line',
            logId: log.id,
            attempt: log.attempt,
            t: formatDurationMs(log.offsetMs),
            message: log.message,
            level: log.level,
        });
    }

    return items;
}

/**
 * Largeur de la barre de durée d'un node (% du node le plus long), avec un
 * plancher de 6 % pour rester visible ; `6 %` aussi sans durée mesurée
 * (rows queued/skipped — la barre n'est alors pas rendue).
 */
export function durationBarWidth(
    durationMs: number | null,
    maxMs: number,
): string {
    if (durationMs === null || maxMs <= 0) {
        return '6%';
    }

    return `${Math.max(6, (durationMs / maxMs) * 100)}%`;
}
