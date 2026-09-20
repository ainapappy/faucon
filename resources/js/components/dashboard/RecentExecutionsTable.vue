<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ExecutionStatusBadge from '@/components/executions/ExecutionStatusBadge.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatDurationMs,
    formatExecutionDate,
    formatTrigger,
} from '@/lib/executionFormat';
import { index as executionsIndex } from '@/routes/workflow-executions';
import type { WorkflowExecutionListItem } from '@/types';

/*
 * « Dernières exécutions » (maquette dashboard.html, D7) : EXACTEMENT la
 * projection de la page Exécutions (D3 — WorkflowExecutionListItem) avec les
 * mêmes helpers de formatage. Ligne cliquable = deep-link `?execution={id}`
 * vers la sheet de détail ; « Tout voir » vers l'historique complet.
 */
const props = defineProps<{
    items: WorkflowExecutionListItem[];
}>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

function openUrl(executionId: number): string {
    return executionsIndex(
        { current_team: teamSlug.value },
        { query: { execution: executionId } },
    ).url;
}

const listUrl = computed(
    () => executionsIndex({ current_team: teamSlug.value }).url,
);
</script>

<template>
    <div
        class="bg-card rounded-lg border shadow-xs"
        data-test="dashboard-recent-executions"
    >
        <!-- En-tête de carte : titre + « Tout voir ». -->
        <div class="flex items-center justify-between gap-2 px-4.5 pt-4 pb-2.5">
            <p class="text-sm leading-none font-semibold">
                Dernières exécutions
            </p>
            <Link
                :href="listUrl"
                class="text-brand-ink hover:text-brand-strong inline-flex items-center gap-1 text-[13px] font-medium"
            >
                Tout voir
                <span aria-hidden="true">→</span>
            </Link>
        </div>

        <div class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/50">
                        <TableHead class="w-24">Exécution</TableHead>
                        <TableHead>Workflow</TableHead>
                        <TableHead class="hidden md:table-cell">
                            Déclencheur
                        </TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead class="hidden sm:table-cell"
                            >Durée</TableHead
                        >
                        <TableHead class="hidden md:table-cell"
                            >Heure</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in items"
                        :key="item.id"
                        class="cursor-pointer"
                        @click="router.visit(openUrl(item.id))"
                    >
                        <TableCell class="font-mono text-xs">
                            #{{ item.id }}
                        </TableCell>
                        <TableCell
                            class="max-w-52 truncate font-medium"
                            :title="item.workflow.name"
                        >
                            {{ item.workflow.name }}
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            {{ formatTrigger(item.triggered_by) }}
                        </TableCell>
                        <TableCell>
                            <ExecutionStatusBadge :status="item.status" />
                        </TableCell>
                        <TableCell
                            class="text-muted-foreground hidden tabular-nums sm:table-cell"
                        >
                            {{ formatDurationMs(item.duration_ms) }}
                        </TableCell>
                        <TableCell
                            class="text-muted-foreground hidden md:table-cell"
                        >
                            {{ formatExecutionDate(item.created_at) }}
                        </TableCell>
                    </TableRow>

                    <!-- Empty state (D7) : aucun run sur l'équipe. -->
                    <TableRow v-if="items.length === 0">
                        <TableCell colspan="6" class="h-28 text-center">
                            <p class="text-muted-foreground text-sm">
                                Aucune exécution pour le moment.
                            </p>
                            <Link
                                :href="listUrl"
                                class="text-brand-ink hover:text-brand-strong mt-1 inline-block text-sm font-medium"
                            >
                                Voir l'historique des exécutions
                            </Link>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
