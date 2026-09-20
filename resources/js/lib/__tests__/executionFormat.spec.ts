import { describe, expect, it } from 'vitest';
import {
    formatDurationMs,
    formatExecutionDate,
    formatTrigger,
} from '@/lib/executionFormat';

describe('formatDurationMs', () => {
    it('returns an em dash for null, undefined and negative values', () => {
        expect(formatDurationMs(null)).toBe('—');
        expect(formatDurationMs(undefined)).toBe('—');
        expect(formatDurationMs(-5)).toBe('—');
    });

    it('formats milliseconds below one second', () => {
        expect(formatDurationMs(0)).toBe('0 ms');
        expect(formatDurationMs(860)).toBe('860 ms');
    });

    it('formats seconds with a French decimal comma', () => {
        expect(formatDurationMs(4120)).toBe('4,1 s');
    });

    it('formats minutes and seconds', () => {
        expect(formatDurationMs(125_000)).toBe('2 min 05 s');
    });
});

describe('formatExecutionDate', () => {
    it('returns an em dash without a date', () => {
        expect(formatExecutionDate(null)).toBe('—');
    });

    it('marks today with the time only', () => {
        const now = new Date();
        now.setHours(12, 4, 0, 0);

        expect(formatExecutionDate(now.toISOString())).toContain(
            'Aujourd’hui · 12:04',
        );
    });

    it('formats other days with a short date', () => {
        // Date locale (pas UTC) : la spec doit passer quel que soit le fuseau.
        const local = new Date(2026, 8, 18, 11, 47, 0);

        expect(formatExecutionDate(local.toISOString())).toMatch(
            /18 sept\..*11:47|11:47/,
        );
    });
});

describe('formatTrigger', () => {
    it('maps the three triggers to their French labels', () => {
        expect(formatTrigger('manual')).toBe('Manuel');
        expect(formatTrigger('webhook')).toBe('Webhook');
        expect(formatTrigger('schedule')).toBe('Planifié');
    });
});
