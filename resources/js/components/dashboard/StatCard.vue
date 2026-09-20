<script setup lang="ts">
import type { StatCardPresentation } from '@/lib/dashboardFormat';

/*
 * Carte KPI du dashboard (maquette dashboard.html corrigée — A5 : valeur +
 * sous-texte réel, plus de sparkline ni de tendance). La valeur arrive
 * pré-formatée depuis lib/dashboardFormat ; le composant ne calcule rien.
 */
defineProps<{
    card: StatCardPresentation;
}>();
</script>

<template>
    <div
        class="bg-card flex flex-col gap-2.5 rounded-lg border p-4 shadow-xs transition-shadow hover:shadow-md"
        data-test="dashboard-stat-card"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground text-[12.5px] font-medium">
                {{ card.label }}
            </span>
            <span
                class="flex size-[30px] flex-none items-center justify-center rounded-md"
                :class="card.iconClasses"
            >
                <component :is="card.icon" class="size-[15px]" />
            </span>
        </div>

        <div
            class="text-[26px] leading-none font-bold tracking-[-0.02em] tabular-nums"
        >
            {{ card.value }}
        </div>

        <div class="text-muted-foreground text-xs">{{ card.subtext }}</div>
    </div>
</template>
