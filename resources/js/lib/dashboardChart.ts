/**
 * Géométrie du graphe « Exécutions » du dashboard (phase 10, D8) — PUR,
 * miroir du computed `chart` de la maquette dashboard.html (aire + polyline).
 *
 * La série reçue est CONTIGUË par contrat (gap-fill côté back) : ce helper ne
 * comble jamais de trous, il tronque les `days` derniers jours. Aucune
 * couleur en dur : le trait et l'aire sont teintés côté composant via les
 * tokens (`--brand`).
 */
import type { DailyExecutions } from '@/types';

/** Viewbox et marges du SVG (valeurs maquette). */
export const CHART_WIDTH = 660;
export const CHART_HEIGHT = 240;
const MARGIN_LEFT = 40;
const MARGIN_RIGHT = 12;
const MARGIN_TOP = 14;
const MARGIN_BOTTOM = 28;

/** Plage du graphe : troncature de la série 30 j (tabs 7 j / 14 j / 30 j). */
export type ChartRange = 7 | 14 | 30;

export type ChartPoint = {
    x: number;
    y: number;
    date: string;
    total: number;
    completed: number;
    failed: number;
};

export type ChartYTick = {
    /** Valeur du tick (arrondie) — sert aussi de clé de rendu. */
    v: number;
    y: number;
    label: string;
};

export type ChartXLabel = {
    text: string;
    x: number;
    show: boolean;
};

export type ChartLayout = {
    area: string;
    line: string;
    points: ChartPoint[];
    yTicks: ChartYTick[];
    labels: ChartXLabel[];
    left: number;
    right: number;
    top: number;
    bottom: number;
};

/** Les `days` derniers jours de la série (le back garantit la contiguïté). */
export function sliceChartRange(
    daily: DailyExecutions[],
    days: ChartRange,
): DailyExecutions[] {
    return daily.slice(-days);
}

/**
 * Nombre compact FR pour les ticks Y (« 1,2 k ») — milliers et plus,
 * valeur brute en dessous.
 */
export function formatCompactNumber(value: number): string {
    return new Intl.NumberFormat('fr-FR', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

/** Label d'axe X maquette : « 19/9 ». */
export function formatDayLabel(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    if (Number.isNaN(day.getTime())) {
        return date;
    }

    return `${day.getDate()}/${day.getMonth() + 1}`;
}

/** Date lisible du tooltip : « 19 sept. ». */
export function formatTooltipDate(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    if (Number.isNaN(day.getTime())) {
        return date;
    }

    return day.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
    });
}

/**
 * Layout complet du graphe : polyline (`M`/`L`, formule maquette), aire
 * fermée, 4 y-ticks, labels X espacés (1 sur N + dernier). `max` = max des
 * totaux × 1,08 avec plancher 1 (série plate à zéro : jamais de /0).
 */
export function buildChartLayout(
    daily: DailyExecutions[],
    days: ChartRange,
): ChartLayout {
    const values = sliceChartRange(daily, days);
    const count = values.length;
    const spanX = CHART_WIDTH - MARGIN_LEFT - MARGIN_RIGHT;
    const spanY = CHART_HEIGHT - MARGIN_TOP - MARGIN_BOTTOM;

    const rawMax = Math.max(0, ...values.map((day) => day.total));
    const max = Math.max(1, rawMax * 1.08);

    const px = (index: number): number =>
        MARGIN_LEFT + (index * spanX) / Math.max(1, count - 1);
    const py = (value: number): number =>
        MARGIN_TOP + (1 - value / max) * spanY;

    const points: ChartPoint[] = values.map((day, index) => ({
        x: px(index),
        y: py(day.total),
        date: day.date,
        total: day.total,
        completed: day.completed,
        failed: day.failed,
    }));

    const line = points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${point.x.toFixed(1)} ${point.y.toFixed(1)}`,
        )
        .join(' ');

    const lastX = points.length > 0 ? px(points.length - 1) : MARGIN_LEFT;
    const area =
        points.length > 0
            ? `${line} L${lastX.toFixed(1)} ${(CHART_HEIGHT - MARGIN_BOTTOM).toFixed(1)} L${MARGIN_LEFT} ${(CHART_HEIGHT - MARGIN_BOTTOM).toFixed(1)} Z`
            : '';

    const yTicks: ChartYTick[] = [0.25, 0.5, 0.75, 1].map((factor) => {
        const v = Math.round(max * factor);

        return { v, y: py(v), label: formatCompactNumber(v) };
    });

    const every = Math.ceil(count / 7);
    const labels: ChartXLabel[] = values.map((day, index) => ({
        text: formatDayLabel(day.date),
        x: px(index),
        show: index % every === 0 || index === count - 1,
    }));

    return {
        area,
        line,
        points,
        yTicks,
        labels,
        left: MARGIN_LEFT,
        right: MARGIN_RIGHT,
        top: MARGIN_TOP,
        bottom: MARGIN_BOTTOM,
    };
}

/**
 * Index du point le plus proche d'une coordonnée X de la viewbox (survol :
 * clampé aux bornes, pas d'espace mort sur les bords).
 */
export function nearestPointIndex(layout: ChartLayout, viewX: number): number {
    const count = layout.points.length;

    if (count === 0) {
        return 0;
    }

    const spanX = CHART_WIDTH - layout.left - layout.right;
    const step = spanX / Math.max(1, count - 1);
    const index = Math.round((viewX - layout.left) / step);

    return Math.min(count - 1, Math.max(0, index));
}
