import { describe, expect, it } from 'vitest';
import { formatAiUsage } from '@/lib/aiUsage';

describe('formatAiUsage', () => {
    it('formate un usage conforme en « Tokens : N prompt · N réponse »', () => {
        expect(
            formatAiUsage({
                text: 'ok',
                usage: { prompt_tokens: 128, completion_tokens: 45 },
            }),
        ).toBe('Tokens : 128 prompt · 45 réponse');
        expect(
            formatAiUsage({
                label: 'lead',
                usage: { prompt_tokens: 0, completion_tokens: 0 },
            }),
        ).toBe('Tokens : 0 prompt · 0 réponse');
    });

    it('tolère les clés supplémentaires dans usage (permissif sur le superflu)', () => {
        expect(
            formatAiUsage({
                usage: {
                    prompt_tokens: 12,
                    completion_tokens: 3,
                    total_tokens: 15,
                },
            }),
        ).toBe('Tokens : 12 prompt · 3 réponse');
    });

    it('renvoie null sans clé usage (sortie d’un autre type de node)', () => {
        expect(formatAiUsage({ text: 'réponse' })).toBeNull();
        expect(formatAiUsage({})).toBeNull();
        expect(formatAiUsage({ usage: null })).toBeNull();
        expect(formatAiUsage({ usage: undefined })).toBeNull();
    });

    it('renvoie null pour une forme usage malformée', () => {
        expect(
            formatAiUsage({
                usage: { prompt_tokens: '128', completion_tokens: 45 },
            }),
        ).toBeNull();
        expect(formatAiUsage({ usage: { prompt_tokens: 128 } })).toBeNull();
        expect(formatAiUsage({ usage: { completion_tokens: 45 } })).toBeNull();
        expect(formatAiUsage({ usage: [128, 45] })).toBeNull();
        expect(formatAiUsage({ usage: '128/45' })).toBeNull();
        expect(formatAiUsage({ usage: 128 })).toBeNull();
    });

    it('renvoie null pour des valeurs non numériques finies', () => {
        expect(
            formatAiUsage({
                usage: { prompt_tokens: Number.NaN, completion_tokens: 45 },
            }),
        ).toBeNull();
        expect(
            formatAiUsage({
                usage: {
                    prompt_tokens: Number.POSITIVE_INFINITY,
                    completion_tokens: 45,
                },
            }),
        ).toBeNull();
    });

    it('renvoie null pour une sortie absente', () => {
        expect(formatAiUsage(null)).toBeNull();
        expect(formatAiUsage(undefined)).toBeNull();
    });
});
