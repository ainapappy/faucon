/**
 * Exécutions persistées (phases 7-8) — miroir des props servies par
 * `WorkflowExecutionController`, des colonnes de `workflow_executions` et de
 * la projection des rows de `workflow_execution_logs`.
 */
import type { ExecutionErrorData } from '@/types';

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

/** Nature d'une row de journal (enum PHP `ExecutionLogKind`). */
export type WorkflowExecutionLogKind = 'node' | 'event';

/** Niveau d'une row de journal (enum PHP `ExecutionLogLevel`). */
export type WorkflowExecutionLogLevel = 'info' | 'ok' | 'error';

/**
 * Statut d'un node dans le journal — `queued` = row pré-insérée à l'ouverture
 * de la tentative (pas encore exécutée), `skipped` = jamais exécuté.
 */
export type WorkflowExecutionNodeLogStatus =
    | 'queued'
    | 'ok'
    | 'error'
    | 'skipped';

/**
 * Une row du journal d'exécution (prop `execution.logs`, phase 8) — miroir de
 * la projection de `WorkflowExecutionController`, ordonnée par id asc (= ordre
 * d'écriture). `input`/`output`/`error` sont déjà redactés et tronqués côté
 * serveur : le front les affiche tels quels, sans second masquage.
 */
export type WorkflowExecutionLogEntry = {
    id: number;
    /** Tentative concernée (1 = premier passage). */
    attempt: number;
    kind: WorkflowExecutionLogKind;
    /** null pour les rows d'événement (kind `'event'`). */
    nodeKey: string | null;
    nodeType: string | null;
    nodeName: string | null;
    /** null pour les rows d'événement. */
    status: WorkflowExecutionNodeLogStatus | null;
    /** null pour queued / skipped / event. */
    durationMs: number | null;
    /** Ligne de journal FR ; null pour queued / skipped. */
    message: string | null;
    level: WorkflowExecutionLogLevel;
    /** Input agrégé reçu par le node (redacté + tronqué côté back). */
    input: Record<string, unknown> | null;
    /** Sortie du node (redactée + tronquée côté back). */
    output: Record<string, unknown> | null;
    error: ExecutionErrorData | null;
    /** Millisecondes écoulées depuis le début de LA TENTATIVE. */
    offsetMs: number;
};

/**
 * Détail d'une exécution sélectionnée (prop `execution`, sheet de détail).
 * `logs` est le journal complet (rows par node + événements de cycle de vie,
 * phase 8) — présent, éventuellement vide, dès la sélection ; `null` tant
 * qu'aucune exécution n'est sélectionnée.
 */
export type WorkflowExecutionDetail = WorkflowExecutionListItem & {
    input: Record<string, unknown> | null;
    logs: WorkflowExecutionLogEntry[];
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
