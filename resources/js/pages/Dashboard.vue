<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import DashboardSkeleton from '@/components/dashboard/DashboardSkeleton.vue';
import ExecutionsChart from '@/components/dashboard/ExecutionsChart.vue';
import RecentExecutionsTable from '@/components/dashboard/RecentExecutionsTable.vue';
import RecentWorkflowsCard from '@/components/dashboard/RecentWorkflowsCard.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import TopWorkflowsCard from '@/components/dashboard/TopWorkflowsCard.vue';
import CreateWorkflowDialog from '@/components/workflows/CreateWorkflowDialog.vue';
import { Button } from '@/components/ui/button';
import { useDashboard } from '@/composables/useDashboard';
import { dashboard } from '@/routes';
import type {
    DashboardAnalytics,
    DashboardInvitation,
    DashboardStats,
    Team,
    TeamPermissions,
    WorkflowExecutionListItem,
    WorkflowSummaryCard,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    stats: DashboardStats;
    recentExecutions: WorkflowExecutionListItem[];
    recentWorkflows: WorkflowSummaryCard[];
    /** Prop DEFER (D1) — undefined au premier rendu, skeletons affichés. */
    analytics?: DashboardAnalytics;
    permissions: TeamPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam
                    ? dashboard(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

/* État dérivé et URLs : composable du domaine — la page assemble seulement. */
const dashboardState = useDashboard(toRef(() => props.stats));

const greeting = computed(
    () => `Bonjour, ${dashboardState.userFirstName.value}`,
);
</script>

<template>
    <Head title="Dashboard" />

    <!-- Conservé tel quel (D1) : invitations d'équipe en attente. -->
    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="space-y-5 px-4 py-6 md:px-8" data-test="dashboard-page">
        <!-- En-tête (maquette corrigée : Templates + Nouveau workflow). -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-xl font-bold tracking-[-0.01em]">
                    {{ greeting }}
                </h1>
                <p class="text-muted-foreground mt-0.5 text-sm">
                    Voici l'activité de vos workflows —
                    {{ dashboardState.welcomeDate.value }}.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="dashboardState.templatesUrl.value">
                        Templates
                    </Link>
                </Button>

                <!-- Gated (découverte 8 — même pattern que workflows/Index). -->
                <CreateWorkflowDialog v-if="permissions.canCreateWorkflow">
                    <Button> Nouveau workflow </Button>
                </CreateWorkflowDialog>
            </div>
        </div>

        <!-- Grille de 4 KPIs (maquette kpi-grid corrigée : 1 → 2 → 4 colonnes). -->
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="card in dashboardState.statCards.value"
                :key="card.label"
                :card="card"
            />
        </div>

        <!-- Zone différée : graphe + colonne droite, skeletons pendant le DEFER. -->
        <Deferred data="analytics">
            <template #fallback>
                <DashboardSkeleton />
            </template>
            <template #default>
                <div
                    v-if="analytics"
                    class="grid gap-3.5 lg:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <ExecutionsChart :daily="analytics.daily" />
                    <div class="grid content-start gap-3.5">
                        <TopWorkflowsCard :items="analytics.topWorkflows" />
                        <RecentWorkflowsCard :items="recentWorkflows">
                            <template #empty-action>
                                <CreateWorkflowDialog
                                    v-if="permissions.canCreateWorkflow"
                                >
                                    <Button variant="outline" size="sm">
                                        Créer le premier workflow
                                    </Button>
                                </CreateWorkflowDialog>
                            </template>
                        </RecentWorkflowsCard>
                    </div>
                </div>
            </template>
        </Deferred>

        <!-- Dernières exécutions (projection identique à la page Exécutions). -->
        <RecentExecutionsTable :items="recentExecutions" />
    </div>
</template>
