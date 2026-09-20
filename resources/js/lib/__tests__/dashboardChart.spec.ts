import { describe, expect, it } from 'vitest';
import {
    buildChartLayout,
    formatCompactNumber,
    formatDayLabel,
    formatTooltipDate,
    nearestPointIndex,
    sliceChartRange,
} from '@/lib/dashboardChart';
import type { DailyExecutions } from '@/types';

/** Série déterministe : 30 jours, totaux 1..30, completed = total - 1. */
function makeDaily(count: number): DailyExecutions[] {
    return Array.from({ length: count }, (_, index) => ({
        date: `2026-08-${String(index + 1).padStart(2, '0')}`,
        total: index + 1,
        completed: index,
        failed: 1,
    }));
}

describe('sliceChartRange', () => {
    it('tronque aux N derniers jours (tabs 7/14/30)', () => {
        const daily = makeDaily(30);

        expect(sliceChartRange(daily, 7)).toHaveLength(7);
        expect(sliceChartRange(daily, 7)[0].date).toBe('2026-08-24');
        expect(sliceChartRange(daily, 14)).toHaveLength(14);
        expect(sliceChartRange(daily, 30)).toHaveLength(30);
    });

    it('rend toute la série si elle est plus courte que la plage', () => {
        expect(sliceChartRange(makeDaily(5), 30)).toHaveLength(5);
    });
});

describe('buildChartLayout', () => {
    it('produit un point par jour, sans rien inventer (contiguïté = contrat back)', () => {
        const layout = buildChartLayout(makeDaily(30), 14);

        expect(layout.points).toHaveLength(14);
        expect(layout.points[0].date).toBe('2026-08-17');
        expect(layout.points.at(-1)?.date).toBe('2026-08-30');
        // Chaque point porte les données complètes (tooltip enrichi).
        expect(layout.points[3]).toMatchObject({
            total: 20,
            completed: 19,
            failed: 1,
        });
    });

    it('bornes de la viewbox maquette : la ligne reste dans les marges', () => {
        const layout = buildChartLayout(makeDaily(30), 30);

        expect(layout.left).toBe(40);
        expect(layout.points[0].x).toBe(40);
        expect(layout.points.at(-1)?.x).toBe(660 - 12);

        for (const point of layout.points) {
            expect(point.y).toBeGreaterThanOrEqual(14);
            expect(point.y).toBeLessThanOrEqual(240 - 28);
        }
    });

    it('propose 4 y-ticks et un maximum à 1,08× le pic', () => {
        const layout = buildChartLayout(makeDaily(30), 30);
        const peak = Math.max(...layout.points.map((point) => point.total));

        expect(layout.yTicks).toHaveLength(4);
        // Le pic (30) reste SOUS la ligne du dernier tick (32) — en SVG,
        // une valeur plus petite = un y plus grand.
        expect(layout.points.at(-1)!.y).toBeGreaterThan(
            layout.yTicks.at(-1)!.y,
        );
        expect(peak).toBe(30);
    });

    it('série plate à zéro : plancher de max à 1, aucune NaN', () => {
        const flat: DailyExecutions[] = Array.from(
            { length: 30 },
            (_, index) => ({
                date: `2026-08-${String(index + 1).padStart(2, '0')}`,
                total: 0,
                completed: 0,
                failed: 0,
            }),
        );

        const layout = buildChartLayout(flat, 30);

        expect(layout.line).toMatch(/^M/);
        expect(layout.line).not.toContain('NaN');
        expect(layout.area.endsWith('Z')).toBe(true);
        expect(layout.points.every((point) => point.y === point.y)).toBe(true);
    });

    it('série à un seul point : pas de division par zéro', () => {
        const layout = buildChartLayout(makeDaily(1), 7);

        expect(layout.points).toHaveLength(1);
        expect(layout.points[0].x).toBe(40);
        expect(layout.line).not.toContain('NaN');
    });

    it('labels X : 1 sur N + dernier, ancrés aux positions des points', () => {
        const layout = buildChartLayout(makeDaily(30), 30);
        const shown = layout.labels.filter((label) => label.show);

        // every = ceil(30/7) = 5 → indices 0, 5, 10, 15, 20, 25 + dernier (29).
        expect(shown).toHaveLength(7);
        expect(layout.labels.at(-1)?.show).toBe(true);
        expect(layout.labels[0].text).toBe('1/8');
    });

    it('aire fermée : la polyline se termine par le tracé du socle et Z', () => {
        const layout = buildChartLayout(makeDaily(30), 14);

        expect(layout.line).toMatch(/^M[\d.]+ [\d.]+( L[\d.]+ [\d.]+)+$/);
        expect(layout.area).toContain(layout.line);
        expect(layout.area.endsWith('Z')).toBe(true);
    });
});

describe('nearestPointIndex', () => {
    it('ramène le point le plus proche et clampe aux bornes', () => {
        const layout = buildChartLayout(makeDaily(30), 7);

        expect(nearestPointIndex(layout, 40)).toBe(0);
        expect(nearestPointIndex(layout, 660)).toBe(6);
        expect(nearestPointIndex(layout, -50)).toBe(0);
    });
});

describe('formatage des axes', () => {
    it('labels FR compacts pour les ticks Y', () => {
        // Intl compact FR : espace insécable (U+00A0) avant l'unité.
        expect(formatCompactNumber(1000)).toBe('1 k');
        expect(formatCompactNumber(1250)).toBe('1,3 k');
        expect(formatCompactNumber(42)).toBe('42');
    });

    it('labels de jours maquette (j/m) et dates de tooltip (19 sept.)', () => {
        expect(formatDayLabel('2026-09-19')).toBe('19/9');
        expect(formatTooltipDate('2026-09-19')).toBe('19 sept.');
    });
});
