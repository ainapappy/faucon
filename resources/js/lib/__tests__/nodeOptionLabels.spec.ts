import { describe, expect, it } from 'vitest';
import { nodeOptionLabel } from '@/lib/nodeOptionLabels';

describe('nodeOptionLabel', () => {
    it('traduit les options de failure_policy en français', () => {
        expect(nodeOptionLabel('fail')).toBe('Échouer');
        expect(nodeOptionLabel('continue')).toBe('Continuer');
    });

    it('renvoie la valeur brute pour toute option sans libellé (operator, method…)', () => {
        expect(nodeOptionLabel('==')).toBe('==');
        expect(nodeOptionLabel('!=')).toBe('!=');
        expect(nodeOptionLabel('GET')).toBe('GET');
        expect(nodeOptionLabel('valeur_inconnue')).toBe('valeur_inconnue');
    });

    it('habille les options de modèle IA composites provider/model (phase 6)', () => {
        expect(nodeOptionLabel('fake/demo')).toBe('demo · Démo');
        expect(nodeOptionLabel('openai/gpt-4o-mini')).toBe(
            'gpt-4o-mini · OpenAI',
        );
        expect(nodeOptionLabel('openai/gpt-4o')).toBe('gpt-4o · OpenAI');
        expect(nodeOptionLabel('anthropic/claude-haiku-4-5')).toBe(
            'claude-haiku-4-5 · Anthropic',
        );
        expect(nodeOptionLabel('anthropic/claude-sonnet-5')).toBe(
            'claude-sonnet-5 · Anthropic',
        );
        expect(nodeOptionLabel('anthropic/claude-opus-5')).toBe(
            'claude-opus-5 · Anthropic',
        );
    });

    it('renvoie la valeur brute pour un fournisseur de modèle inconnu', () => {
        expect(nodeOptionLabel('acme/modele-x')).toBe('acme/modele-x');
        expect(nodeOptionLabel('/sans-fournisseur')).toBe('/sans-fournisseur');
    });
});
