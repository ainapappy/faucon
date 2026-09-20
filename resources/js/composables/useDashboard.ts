/**
 * État dérivé de la page Dashboard (phase 10, D7) : KPIs pré-formatés,
 * en-tête d'accueil et URLs Wayfinder. La page appelle et rend — elle ne
 * transforme jamais les props elle-même.
 */
import { usePage } from '@inertiajs/vue3';
import { computed, type Ref } from 'vue';
import {
    buildStatCards,
    formatFirstName,
    formatWelcomeDate,
    type StatCardPresentation,
} from '@/lib/dashboardFormat';
import { index as templatesIndex } from '@/routes/templates';
import { index as executionsIndex } from '@/routes/workflow-executions';
import type { DashboardStats } from '@/types';

export function useDashboard(stats: Ref<DashboardStats>) {
    const page = usePage();

    const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

    /** Les 4 cartes KPI de la maquette corrigée (valeur + sous-texte réels). */
    const statCards = computed<StatCardPresentation[]>(() =>
        buildStatCards(stats.value),
    );

    /** « Bonjour, {prénom} » + date du jour (maquette page-header). */
    const userFirstName = computed(() =>
        formatFirstName(page.props.auth.user.name),
    );

    const welcomeDate = computed(() => formatWelcomeDate());

    /**
     * Historique des exécutions — avec deep-link `?execution={id}` quand une
     * exécution est visée (même contrat que la page Exécutions, D3).
     */
    function executionsUrl(executionId?: number): string {
        return executionsIndex(
            { current_team: teamSlug.value },
            { query: executionId ? { execution: executionId } : {} },
        ).url;
    }

    /** Raccourci « Templates » du bandeau d'actions. */
    const templatesUrl = computed(
        () => templatesIndex({ current_team: teamSlug.value }).url,
    );

    return {
        teamSlug,
        statCards,
        userFirstName,
        welcomeDate,
        executionsUrl,
        templatesUrl,
    };
}
