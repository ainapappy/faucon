<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue';
import {
    buildChartLayout,
    formatTooltipDate,
    nearestPointIndex,
    CHART_HEIGHT,
    CHART_WIDTH,
    type ChartRange,
} from '@/lib/dashboardChart';
import { formatDashboardNumber } from '@/lib/dashboardFormat';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { DailyExecutions } from '@/types';

/*
 * Graphe « Exécutions » du dashboard (maquette corrigée, D8) — SVG statique
 * maison, AIRE + polyline, tabs 7 j / 14 j / 30 j (troncature de la série 30 j
 * contiguë servie par le back : le front ne comble jamais de trous).
 *
 * Dataviz : une seule série → pas de légende (le titre la nomme) ; grille
 * discrète, 4 y-ticks, labels X espacés ; survol = crosshair + tooltip (date,
 * total, répartition réussies/échecs). Trait et aire teintés par les tokens
 * (`--brand`) — aucune couleur de donnée en dur ; les textes restent en
 * tokens de texte, jamais à la couleur de la série.
 */
const props = defineProps<{
    daily: DailyExecutions[];
}>();

const RANGES: { value: ChartRange; label: string }[] = [
    { value: 7, label: '7 j' },
    { value: 14, label: '14 j' },
    { value: 30, label: '30 j' },
];

const range = ref<ChartRange>(14);
const hoverIndex = ref<number | null>(null);

/* Tabs reka-ui en string — pont typé vers la plage numérique du helper. */
const rangeValue = computed<string>({
    get: () => String(range.value),
    set: (value) => {
        range.value = Number(value) as ChartRange;
    },
});

const chart = useTemplateRef<SVGSVGElement>('chart-svg');

const layout = computed(() => buildChartLayout(props.daily, range.value));

const hoveredPoint = computed(() =>
    hoverIndex.value === null
        ? null
        : (layout.value.points[hoverIndex.value] ?? null),
);

/* Tooltip positionné en % de la viewbox — aucun calcul de taille DOM. */
const tooltipStyle = computed(() => {
    if (!hoveredPoint.value) {
        return {};
    }

    const left = Math.min(
        84,
        Math.max(16, (hoveredPoint.value.x / CHART_WIDTH) * 100),
    );

    return {
        left: `${left}%`,
        top: `${(hoveredPoint.value.y / CHART_HEIGHT) * 100}%`,
    };
});

function onChartMove(event: MouseEvent): void {
    const element = chart.value;

    if (!element) {
        return;
    }

    const rect = element.getBoundingClientRect();
    const viewX = (event.clientX - rect.left) * (CHART_WIDTH / rect.width);

    hoverIndex.value = nearestPointIndex(layout.value, viewX);
}
</script>

<template>
    <div class="bg-card rounded-lg border p-4.5 shadow-xs">
        <!-- En-tête : titre + tabs de plage (maquette chart-head). -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm leading-none font-semibold">Exécutions</p>
                <p class="text-muted-foreground mt-1.5 text-[13px]">
                    Volume quotidien et tendance
                </p>
            </div>

            <Tabs
                v-model="rangeValue"
                class="gap-0"
                data-test="dashboard-chart-tabs"
            >
                <TabsList>
                    <TabsTrigger
                        v-for="option in RANGES"
                        :key="option.value"
                        :value="String(option.value)"
                        class="px-3 text-[13px]"
                    >
                        {{ option.label }}
                    </TabsTrigger>
                </TabsList>
            </Tabs>
        </div>

        <div
            class="relative mt-3"
            data-test="dashboard-chart"
            @mouseleave="hoverIndex = null"
        >
            <svg
                ref="chart-svg"
                class="block h-auto w-full"
                :viewBox="`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`"
                role="img"
                aria-label="Exécutions par jour"
                @mousemove="onChartMove"
            >
                <!-- Grille + ticks Y (recessive : bordure en dash, texte muted). -->
                <g
                    v-for="(tick, tickIndex) in layout.yTicks"
                    :key="`grid-${tickIndex}`"
                >
                    <line
                        class="stroke-border"
                        :x1="layout.left"
                        :x2="CHART_WIDTH - layout.right"
                        :y1="tick.y"
                        :y2="tick.y"
                        stroke-dasharray="3 5"
                    />
                    <text
                        class="fill-muted-foreground text-[10.5px]"
                        :x="layout.left - 7"
                        :y="tick.y + 3.5"
                        text-anchor="end"
                    >
                        {{ tick.label }}
                    </text>
                </g>

                <!-- Aire + ligne (tokens, teinte dérivée — jamais en dur). -->
                <path class="fill-brand/14" :d="layout.area" />
                <path
                    class="stroke-brand fill-none"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :d="layout.line"
                />

                <!-- Labels X espacés (1 sur N + dernier). -->
                <template
                    v-for="(label, index) in layout.labels"
                    :key="`x-${index}`"
                >
                    <text
                        v-if="label.show"
                        class="fill-muted-foreground text-[10.5px]"
                        :x="label.x"
                        y="232"
                        text-anchor="middle"
                    >
                        {{ label.text }}
                    </text>
                </template>

                <!-- Survol : crosshair + marqueur. -->
                <g v-if="hoveredPoint">
                    <line
                        class="stroke-muted-foreground"
                        stroke-dasharray="2 3"
                        :x1="hoveredPoint.x"
                        :x2="hoveredPoint.x"
                        :y1="layout.top"
                        :y2="CHART_HEIGHT - layout.bottom"
                    />
                    <circle
                        class="fill-background stroke-brand"
                        stroke-width="2.5"
                        :cx="hoveredPoint.x"
                        :cy="hoveredPoint.y"
                        r="4.5"
                    />
                </g>
            </svg>

            <!-- Tooltip enrichi (D7) : total + répartition réussies / échecs. -->
            <div
                v-if="hoveredPoint"
                class="bg-popover pointer-events-none absolute z-10 -translate-x-1/2 translate-y-[-115%] rounded-md border px-2.5 py-2 shadow-md"
                :style="tooltipStyle"
                data-test="dashboard-chart-tooltip"
            >
                <div class="text-muted-foreground text-xs">
                    {{ formatTooltipDate(hoveredPoint.date) }}
                </div>
                <div class="text-[13px] font-semibold tabular-nums">
                    {{ formatDashboardNumber(hoveredPoint.total) }}
                    {{ hoveredPoint.total > 1 ? 'exécutions' : 'exécution' }}
                </div>
                <div class="text-muted-foreground text-xs tabular-nums">
                    {{ formatDashboardNumber(hoveredPoint.completed) }}
                    réussies ·
                    {{ formatDashboardNumber(hoveredPoint.failed) }} échecs
                </div>
            </div>
        </div>
    </div>
</template>
