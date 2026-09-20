/**
 * Exécutions persistées (phase 7) — miroir des props servies par
 * `WorkflowExecutionController` et des colonnes de `workflow_executions`.
 */
import type { ExecutionErrorData, ExecutionResult } from '@/types';

/** Statut persisté d'une exécution (enum PHP `ExecutionStatus`). */
export type WorkflowExecutionStatus =
    | 'pending'
    | 'running'
    | 'completed'
    | 'failed'
    | 'cancelled';

/** Déclencheur d'une exécution persistée (enum PHP `ExecutionTrigger`). */
export type WorkflowExecutionTrigger = 'manual' | 'webhook' | 'schedule';

/** Ligne de l'historique paginé (prop `executions.data[*]`). */
export type WorkflowExecutionListItem = {
    id: number;
    status: WorkflowExecutionStatus;
    triggered_by: WorkflowExecutionTrigger;
    /** Tentative courante (1 = premier passage, 2 = après un retry). */
    attempt: number;
    /** Durée murale de la dernière tentative, en millisecondes. */
    duration_ms: number | null;
    created_at: string;
    workflow: { id: number; name: string };
};

/**
 * Détail d'une exécution sélectionnée (prop `execution`, sheet de détail).
 * `result` est le résultat complet du moteur — timeline des nodes incluse ;
 * `null` tant que le run n'a pas démarré.
 */
export type WorkflowExecutionDetail = WorkflowExecutionListItem & {
    input: Record<string, unknown> | null;
    result: ExecutionResult | null;
    error: ExecutionErrorData | null;
    started_at: string | null;
    finished_at: string | null;
};

/** Option du select workflows de la toolbar (filtre par workflow). */
export type WorkflowOption = { id: number; name: string };

/** Filtres serveur renvoyés tels quels (prop `filters`). */
export type ExecutionFilters = {
    workflow_id: number | null;
    status: WorkflowExecutionStatus | null;
    q: string;
};

/** Serializeur Inertia du paginator Laravel (aplati, pas de wrapper meta). */
export type PaginatedExecutions = {
    data: WorkflowExecutionListItem[];
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
};
