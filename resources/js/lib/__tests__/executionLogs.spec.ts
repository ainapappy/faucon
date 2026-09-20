import { describe, expect, it } from 'vitest';
import {
    buildJournal,
    buildTimeline,
    durationBarWidth,
} from '@/lib/executionLogs';
import type {
    WorkflowExecutionDetail,
    WorkflowExecutionLogEntry,
} from '@/types';

/** Row de journal par défaut (node ok d'une première tentative). */
function logRow(
    overrides: Partial<WorkflowExecutionLogEntry> = {},
): WorkflowExecutionLogEntry {
    return {
        id: 1,
        attempt: 1,
        kind: 'node',
        nodeKey: 'trigger',
        nodeType: 'trigger.webhook',
        nodeName: 'Nouveau formulaire',
        status: 'ok',
        durationMs: 45,
        message: 'Nouveau formulaire terminé (45 ms).',
        level: 'ok',
        input: { form: 'contact' },
        output: { accepted: true },
        error: null,
        offsetMs: 0,
        ...overrides,
    };
}

/** Détail d'exécution par défaut (tentative 1, sans logs). */
function execution(
    overrides: Partial<WorkflowExecutionDetail> = {},
): WorkflowExecutionDetail {
    return {
        id: 42,
        status: 'completed',
        triggered_by: 'webhook',
        attempt: 1,
        duration_ms: 2312,
        created_at: '2026-09-20T10:00:00+02:00',
        workflow: { id: 7, name: 'Veille concurrentielle' },
        input: { form: 'contact' },
        logs: [],
        error: null,
        started_at: '2026-09-20T10:00:00+02:00',
        finished_at: '2026-09-20T10:00:02+02:00',
        ...overrides,
    };
}

describe('buildTimeline', () => {
    it('keeps only the node rows of the current attempt, in write order', () => {
        const result = buildTimeline(
            execution({
                attempt: 2,
                logs: [
                    logRow({
                        id: 1,
                        attempt: 1,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Exécution démarrée (déclencheur : Webhook).',
                        level: 'info',
                    }),
                    logRow({ id: 2, attempt: 1, message: 'Tentative 1.' }),
                    logRow({
                        id: 3,
                        attempt: 1,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Retry programmé (tentative 2/2 dans 30 s).',
                        level: 'info',
                    }),
                    logRow({
                        id: 4,
                        attempt: 2,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Exécution démarrée (déclencheur : Webhook).',
                        level: 'info',
                    }),
                    logRow({
                        id: 5,
                        attempt: 2,
                        nodeKey: 'classify',
                        nodeName: 'Classifier',
                        status: 'ok',
                        durationMs: 388,
                    }),
                    logRow({
                        id: 6,
                        attempt: 2,
                        nodeKey: 'notify',
                        nodeName: 'Notifier',
                        status: 'error',
                        durationMs: 415,
                        level: 'error',
                        message: 'HTTP 429.',
                        error: {
                            nodeKey: 'notify',
                            type: 'action.http',
                            reason: 'http_error',
                            message: 'HTTP 429.',
                        },
                    }),
                ],
            }),
        );

        expect(result.map((row) => row.id)).toEqual([5, 6]);
    });

    it('keeps queued and skipped rows of the current attempt', () => {
        const result = buildTimeline(
            execution({
                logs: [
                    logRow({
                        id: 1,
                        nodeKey: 'a',
                        nodeName: 'A',
                        status: 'ok',
                        durationMs: 100,
                    }),
                    logRow({
                        id: 2,
                        nodeKey: 'b',
                        nodeName: 'B',
                        status: 'queued',
                        durationMs: null,
                        message: null,
                        input: null,
                        output: null,
                    }),
                    logRow({
                        id: 3,
                        nodeKey: 'c',
                        nodeName: 'C',
                        status: 'skipped',
                        durationMs: null,
                        message: null,
                        input: null,
                        output: null,
                    }),
                ],
            }),
        );

        expect(result.map((row) => row.status)).toEqual([
            'ok',
            'queued',
            'skipped',
        ]);
    });

    it('returns an empty list for an execution without logs', () => {
        expect(buildTimeline(execution({ logs: [] }))).toEqual([]);
    });

    it('exposes redacted payloads as-is (masking is a backend concern)', () => {
        const input = { api_key: '[masqué]', body: 'Données tronqu…' };
        const output = {
            _payload_bytes: 81234,
            _note: 'Contenu omis : dépasse la taille maximale de journalisation.',
        };
        const [row] = buildTimeline(
            execution({ logs: [logRow({ input, output })] }),
        );

        expect(row?.input).toBe(input);
        expect(row?.output).toBe(output);
    });
});

describe('buildJournal', () => {
    it('lists every message row with a per-attempt offset and its level', () => {
        const result = buildJournal(
            execution({
                logs: [
                    logRow({
                        id: 1,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Exécution démarrée (déclencheur : Webhook).',
                        level: 'info',
                        offsetMs: 0,
                    }),
                    logRow({
                        id: 2,
                        message: 'Nouveau formulaire terminé (45 ms).',
                        level: 'ok',
                        offsetMs: 860,
                    }),
                ],
            }),
        );

        expect(result).toEqual([
            {
                kind: 'line',
                logId: 1,
                attempt: 1,
                t: '0 ms',
                message: 'Exécution démarrée (déclencheur : Webhook).',
                level: 'info',
            },
            {
                kind: 'line',
                logId: 2,
                attempt: 1,
                t: '860 ms',
                message: 'Nouveau formulaire terminé (45 ms).',
                level: 'ok',
            },
        ]);
    });

    it('inserts a « Tentative N » separator at every attempt change', () => {
        const result = buildJournal(
            execution({
                attempt: 2,
                logs: [
                    logRow({
                        id: 1,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        attempt: 1,
                        message: 'Exécution démarrée (déclencheur : Webhook).',
                        level: 'info',
                    }),
                    logRow({
                        id: 2,
                        attempt: 1,
                        message: 'HTTP 429.',
                        level: 'error',
                    }),
                    logRow({
                        id: 3,
                        attempt: 1,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Retry programmé (tentative 2/2 dans 30 s).',
                        level: 'info',
                    }),
                    logRow({
                        id: 4,
                        attempt: 2,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Exécution démarrée (déclencheur : Webhook).',
                        level: 'info',
                    }),
                ],
            }),
        );

        expect(result).toMatchObject([
            { kind: 'separator', attempt: 1 },
            { kind: 'line', logId: 1, attempt: 1 },
            { kind: 'line', logId: 2, attempt: 1, level: 'error' },
            { kind: 'line', logId: 3, attempt: 1 },
            { kind: 'separator', attempt: 2 },
            { kind: 'line', logId: 4, attempt: 2 },
        ]);
    });

    it('adds no separator while the execution is on its first attempt', () => {
        const result = buildJournal(
            execution({
                logs: [
                    logRow({
                        message: 'Exécution terminée avec succès.',
                        level: 'ok',
                    }),
                ],
            }),
        );

        expect(result).toEqual([
            {
                kind: 'line',
                logId: 1,
                attempt: 1,
                t: '0 ms',
                message: 'Exécution terminée avec succès.',
                level: 'ok',
            },
        ]);
    });

    it('skips message-less rows (queued and skipped nodes)', () => {
        const result = buildJournal(
            execution({
                logs: [
                    logRow({
                        id: 1,
                        nodeKey: 'a',
                        status: 'ok',
                        message: 'A terminé.',
                        offsetMs: 120,
                    }),
                    logRow({
                        id: 2,
                        nodeKey: 'b',
                        status: 'queued',
                        message: null,
                        offsetMs: 130,
                    }),
                    logRow({
                        id: 3,
                        nodeKey: 'c',
                        status: 'skipped',
                        message: null,
                        offsetMs: 140,
                    }),
                    logRow({
                        id: 4,
                        kind: 'event',
                        nodeKey: null,
                        nodeName: null,
                        nodeType: null,
                        status: null,
                        durationMs: null,
                        message: 'Exécution terminée avec succès.',
                        level: 'ok',
                        offsetMs: 150,
                    }),
                ],
            }),
        );

        expect(
            result.map((item) => (item.kind === 'line' ? item.logId : null)),
        ).toEqual([1, 4]);
    });

    it('returns an empty list for an execution without logs', () => {
        expect(buildJournal(execution({ logs: [] }))).toEqual([]);
    });
});

describe('durationBarWidth', () => {
    it('scales the duration against the longest step', () => {
        expect(durationBarWidth(500, 1000)).toBe('50%');
        expect(durationBarWidth(1000, 1000)).toBe('100%');
    });

    it('floors tiny ratios to keep the bar visible', () => {
        expect(durationBarWidth(10, 1000)).toBe('6%');
    });

    it('floors null durations (queued and skipped nodes)', () => {
        expect(durationBarWidth(null, 1000)).toBe('6%');
    });
});
