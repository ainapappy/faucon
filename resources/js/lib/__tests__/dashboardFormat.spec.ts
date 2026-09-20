import { Activity, CircleCheck, Timer, Workflow } from '@lucide/vue';
import { describe, expect, it } from 'vitest';
import {
    buildStatCards,
    formatDashboardNumber,
    formatFirstName,
    formatSuccessRate,
    topWorkflowBars,
    workflowLastRunLabel,
} from '@/lib/dashboardFormat';
import type { DashboardStats, TopWorkflow, WorkflowSummaryCard } from '@/types';

const STATS: DashboardStats = {
    activeWorkflows: 3,
    totalWorkflows: 5,
    executions24h: 1284,
    executions7d: 8902,
    successRate7d: 98.2,
    failures7d: 47,
    averageDurationMs7d: 4820,
};

describe('buildStatCards', () => {
    it('les 4 KPIs de la maquette corrigée, dans le bon ordre (D1)', () => {
        const cards = buildStatCards(STATS);

        expect(cards.map((card) => card.label)).toEqual([
            'Exécutions · 24 h',
            'Taux de succès · 7 j',
            'Workflows actifs',
            'Durée moyenne · 7 j',
        ]);
        expect(cards[0]!.icon).toBe(Activity);
        expect(cards[1]!.icon).toBe(CircleCheck);
        expect(cards[2]!.icon).toBe(Workflow);
        expect(cards[3]!.icon).toBe(Timer);
    });

    it('valeurs pré-formatées FR + sous-textes réels (arbitrage A5)', () => {
        const [executions, success, workflows, duration] =
            buildStatCards(STATS);

        expect(executions!.value).toBe('1 284');
        expect(executions!.subtext).toBe('8 902 sur 7 j');
        expect(success!.value).toBe('98,2 %');
        expect(success!.subtext).toBe('dont 47 échecs');
        expect(workflows!.value).toBe('3');
        expect(workflows!.subtext).toBe('sur 5 workflows');
        expect(duration!.value).toBe('4,8 s');
        expect(duration!.subtext).toBe('exécutions réussies');
    });

    it('cas limites null : « — » pour le taux et la durée', () => {
        const cards = buildStatCards({
            ...STATS,
            successRate7d: null,
            averageDurationMs7d: null,
        });

        expect(cards[1]!.value).toBe('—');
        expect(cards[3]!.value).toBe('—');
    });
});

describe('topWorkflowBars', () => {
    it('barres proportionnelles au maximum', () => {
        const items: TopWorkflow[] = [
            { id: 1, name: 'A', runs: 400 },
            { id: 2, name: 'B', runs: 200 },
        ];

        expect(topWorkflowBars(items).map((bar) => bar.widthPercent)).toEqual([
            '100%',
            '50%',
        ]);
    });

    it('plancher de 1 : série plate à zéro sans division par zéro', () => {
        const items: TopWorkflow[] = [
            { id: 1, name: 'A', runs: 0 },
            { id: 2, name: 'B', runs: 0 },
        ];

        expect(topWorkflowBars(items).map((bar) => bar.widthPercent)).toEqual([
            '0%',
            '0%',
        ]);
    });
});

describe('formatSuccessRate', () => {
    it('pourcentage à 1 décimale FR, « — » sans donnée', () => {
        expect(formatSuccessRate(80)).toBe('80,0 %');
        expect(formatSuccessRate(98.26)).toBe('98,3 %');
        expect(formatSuccessRate(null)).toBe('—');
    });
});

describe('libellés divers', () => {
    it('prénom pour le « Bonjour, … »', () => {
        expect(formatFirstName('Aina Papy')).toBe('Aina');
        expect(formatFirstName('Aina')).toBe('Aina');
    });

    it('nombre formaté FR (séparateur de milliers = espace insécable étroit)', () => {
        expect(formatDashboardNumber(8902)).toBe('8 902');
    });

    it('méta du dernier run (WorkflowSummaryCard)', () => {
        const workflow = {
            id: 1,
            name: 'A',
            status: 'active',
            nodesCount: 4,
            lastExecution: {
                status: 'completed',
                createdAt: '2026-09-20T10:00:00Z',
            },
        } as WorkflowSummaryCard;

        expect(workflowLastRunLabel(workflow)).toContain(
            'Dernière exécution :',
        );
        expect(
            workflowLastRunLabel({
                ...workflow,
                lastExecution: null,
            }),
        ).toBe('Jamais exécutée');
    });
});
