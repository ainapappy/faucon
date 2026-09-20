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
});
