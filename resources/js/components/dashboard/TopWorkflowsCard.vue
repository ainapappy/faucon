<script setup lang="ts">
import { computed } from 'vue';
import { formatDashboardNumber, topWorkflowBars } from '@/lib/dashboardFormat';
import type { TopWorkflow } from '@/types';

/*
 * « Workflows en tête · 7 j » (maquette corrigée) : barres proportionnelles
 * au maximum — le ratio est calculé dans lib/dashboardFormat, pas ici.
 */
const props = defineProps<{
    items: TopWorkflow[];
}>();

const bars = computed(() => topWorkflowBars(props.items));
</script>

<template>
    <div
        class="bg-card rounded-lg border p-4.5 pb-3 shadow-xs"
        data-test="dashboard-top-workflows"
    >
        <p class="mb-2.5 text-sm leading-none font-semibold">
            Workflows en tête · 7 j
        </p>

        <!-- Empty state : aucune exécution sur la période. -->
        <p v-if="items.length === 0" class="text-muted-foreground py-2 text-sm">
            Aucune exécution sur les 7 derniers jours.
        </p>

        <div v-else class="grid gap-2.5">
            <div v-for="bar in bars" :key="bar.id">
                <div
                    class="mb-1 flex items-baseline justify-between gap-2 text-[12.5px]"
                >
                    <span class="truncate font-medium" :title="bar.name">
                        {{ bar.name }}
                    </span>
                    <span class="text-muted-foreground shrink-0 tabular-nums">
                        {{ formatDashboardNumber(bar.runs) }} runs
                    </span>
                </div>
                <div class="bg-muted h-1.25 overflow-hidden rounded-full">
                    <i
                        class="bg-brand block h-full rounded-full transition-[width] duration-500 ease-out"
                        :style="{ width: bar.widthPercent }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
