<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ExecutionStatusBadge from '@/components/executions/ExecutionStatusBadge.vue';
import { workflowLastRunLabel } from '@/lib/dashboardFormat';
import { edit } from '@/routes/workflows';
import type { WorkflowSummaryCard } from '@/types';

/*
 * « Vos workflows » (phase 10, A3 — remplace l'« Activité en direct » de la
 * maquette) : accès rapide aux 6 derniers workflows, lien éditeur, badge du
 * dernier run. Le statut Actif/En pause suit le pattern du dot WorkflowCard
 * (dot + libellé en title — jamais la couleur seule).
 */
const props = defineProps<{
    items: WorkflowSummaryCard[];
}>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

function editUrl(workflowId: number): string {
    return edit({ current_team: teamSlug.value, workflow: workflowId }).url;
}
</script>

<template>
    <div
        class="bg-card rounded-lg border p-4.5 pb-2.5 shadow-xs"
        data-test="dashboard-recent-workflows"
    >
        <p class="mb-2.5 text-sm leading-none font-semibold">Vos workflows</p>

        <!-- Empty state (D7) : équipe sans workflow, action fournie par la page. -->
        <div
            v-if="items.length === 0"
            class="pb-2"
            data-test="recent-workflows-empty"
        >
            <p class="text-muted-foreground text-sm">
                Aucun workflow — créez le premier.
            </p>
            <div v-if="$slots['empty-action']" class="mt-2.5">
                <slot name="empty-action" />
            </div>
        </div>

        <div v-else class="grid">
            <Link
                v-for="workflow in items"
                :key="workflow.id"
                :href="editUrl(workflow.id)"
                class="hover:bg-accent flex items-center gap-2.5 rounded-md px-2 py-2 transition-colors"
            >
                <span
                    class="size-2 flex-none rounded-full"
                    :class="
                        workflow.status === 'active'
                            ? 'bg-success'
                            : 'bg-muted-foreground'
                    "
                    :title="workflow.status === 'active' ? 'Actif' : 'En pause'"
                />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-medium">
                        {{ workflow.name }}
                    </span>
                    <small class="text-muted-foreground text-xs">
                        {{ workflowLastRunLabel(workflow) }}
                        ·
                        {{ workflow.nodesCount }} nodes
                    </small>
                </span>
                <ExecutionStatusBadge
                    v-if="workflow.lastExecution"
                    :status="workflow.lastExecution.status"
                />
            </Link>
        </div>
    </div>
</template>
