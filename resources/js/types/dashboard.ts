/**
 * Tableau de bord (phase 10, D1/D10) — miroir des props servies par
 * `DashboardController` et des DTO `app/Data/Workflow/Dashboard*`.
 */
import type { WorkflowExecutionStatus } from '@/types';

/** KPIs de l'équipe (prop SYNC `stats` — DTO DashboardStats). */
export type DashboardStats = {
    /** workflows.status = 'active'. */
    activeWorkflows: number;
    /** Sous-texte du KPI « Workflows actifs » : « sur N workflows ». */
    totalWorkflows: number;
    executions24h: number;
    /** Sous-texte du KPI 24 h : « N sur 7 j ». */
    executions7d: number;
    /**
     * % (1 décimale) — completed / (completed + failed) sur 7 j, cancelled
     * hors dénominateur. null = rien de terminé → le front affiche « — ».
     */
    successRate7d: number | null;
    /** Sous-texte du KPI succès : « dont N échecs ». */
    failures7d: number;
    /** Moyenne des durées des exécutions *completed* 7 j. null → « — ». */
    averageDurationMs7d: number | null;
};

/**
 * Ligne « Vos workflows » (prop SYNC `recentWorkflows`, limite 6) — les 6
 * workflows mis à jour le plus récemment, avec leur dernier run.
 */
export type WorkflowSummaryCard = {
    id: number;
    name: string;
    status: 'draft' | 'active';
    nodesCount: number;
    lastExecution: {
        status: WorkflowExecutionStatus;
        createdAt: string;
    } | null;
};

/** Un jour de la série 30 j (prop DEFER `analytics.daily`) — date 'YYYY-MM-DD'. */
export type DailyExecutions = {
    date: string;
    total: number;
    completed: number;
    failed: number;
};

/** Ligne de « Workflows en tête · 7 j » (prop DEFER `analytics.topWorkflows`). */
export type TopWorkflow = {
    id: number;
    name: string;
    runs: number;
};

/**
 * Série journalière + top workflows (prop DEFER `analytics` — DTO
 * DashboardAnalytics). `daily` est CONTIGUË par contrat (gap-fill côté back) :
 * le front ne comble jamais de trous et se contente de tronquer 7/14/30.
 */
export type DashboardAnalytics = {
    daily: DailyExecutions[];
    topWorkflows: TopWorkflow[];
};
