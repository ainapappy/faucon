/**
 * Présentation pure du dashboard (phase 10, D7) — formatage des KPIs et
 * largeur des barres « Workflows en tête ». Aucun état, aucune dépendance
 * Vue : les composants appellent et rendent.
 */
import { Activity, CircleCheck, Timer, Workflow } from '@lucide/vue';
import type { Component } from 'vue';
import { formatDurationMs, formatExecutionDate } from '@/lib/executionFormat';
import type { DashboardStats, TopWorkflow, WorkflowSummaryCard } from '@/types';

/** Carte KPI prête à rendre (props de StatCard). */
export type StatCardPresentation = {
    label: string;
    value: string;
    subtext: string;
    icon: Component;
    /** Classe du pastille d'icône (catégorielle, ordre fixe — jamais seule). */
    iconClasses: string;
};

const numberFormat = new Intl.NumberFormat('fr-FR');

/** « 1 284 » */
export function formatDashboardNumber(value: number): string {
    return numberFormat.format(value);
}

/** « 98,2 % » — 1 décimale imposée, miroir de la maquette. */
export function formatSuccessRate(rate: number | null): string {
    if (rate === null) {
        return '—';
    }

    return `${rate.toLocaleString('fr-FR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })} %`;
}

/** « mardi 19 septembre » — sous-titre de l'en-tête. */
export function formatWelcomeDate(now: Date = new Date()): string {
    return now.toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

/** Prénom de l'utilisateur (« Bonjour, Aina »). */
export function formatFirstName(fullName: string): string {
    return fullName.trim().split(/\s+/)[0] ?? fullName;
}

/**
 * Les 4 KPIs de la maquette corrigée (ordre D1 : exécutions 24 h, taux de
 * succès, workflows actifs, durée moyenne) — valeur pré-formatée + sous-texte
 * réel (arbitrage A5 : plus de sparkline ni de tendance fictive).
 */
export function buildStatCards(stats: DashboardStats): StatCardPresentation[] {
    return [
        {
            label: 'Exécutions · 24 h',
            value: formatDashboardNumber(stats.executions24h),
            subtext: `${formatDashboardNumber(stats.executions7d)} sur 7 j`,
            icon: Activity,
            iconClasses: 'bg-cat-1/13 text-cat-1',
        },
        {
            label: 'Taux de succès · 7 j',
            value: formatSuccessRate(stats.successRate7d),
            subtext: `dont ${formatDashboardNumber(stats.failures7d)} échecs`,
            icon: CircleCheck,
            iconClasses: 'bg-cat-4/12 text-cat-4',
        },
        {
            label: 'Workflows actifs',
            value: formatDashboardNumber(stats.activeWorkflows),
            subtext: `sur ${formatDashboardNumber(stats.totalWorkflows)} workflows`,
            icon: Workflow,
            iconClasses: 'bg-cat-2/12 text-cat-2',
        },
        {
            label: 'Durée moyenne · 7 j',
            value:
                stats.averageDurationMs7d === null
                    ? '—'
                    : formatDurationMs(stats.averageDurationMs7d),
            subtext: 'exécutions réussies',
            icon: Timer,
            iconClasses: 'bg-cat-3/12 text-cat-3',
        },
    ];
}

export type TopWorkflowBar = {
    id: number;
    name: string;
    runs: number;
    /** Largeur de barre en % — ratio au maximum (plancher 1). */
    widthPercent: string;
};

/** Barres proportionnelles de « Workflows en tête · 7 j » (ratio au max). */
export function topWorkflowBars(items: TopWorkflow[]): TopWorkflowBar[] {
    const max = Math.max(1, ...items.map((item) => item.runs));

    return items.map((item) => ({
        id: item.id,
        name: item.name,
        runs: item.runs,
        widthPercent: `${Math.round((item.runs / max) * 100)}%`,
    }));
}

/** Méta d'une ligne « Vos workflows » : dernière exécution lisible. */
export function workflowLastRunLabel(workflow: WorkflowSummaryCard): string {
    if (!workflow.lastExecution) {
        return 'Jamais exécutée';
    }

    return `Dernière exécution : ${formatExecutionDate(
        workflow.lastExecution.createdAt,
    )}`;
}
