import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import {
    usePasswordMatch,
    usePasswordStrength,
} from '@/composables/usePasswordValidation';

describe('usePasswordStrength', () => {
    it('reste au score 0 (libellé « — ») pour un mot de passe vide ou trop court', () => {
        const password = ref('');
        const { strength } = usePasswordStrength(password);

        expect(strength.value).toEqual({
            score: 0,
            label: '—',
            color: 'var(--muted-foreground)',
        });

        password.value = 'abcde';
        expect(strength.value.score).toBe(0);
    });

    it('suit le scoring de la maquette register : longueur, casse mixte, chiffre + symbole', () => {
        const cases: Array<[string, number, string]> = [
            ['abcdefgh', 1, 'Faible'], // 8 caractères minuscules
            ['Abcdefgh', 2, 'Correct'], // + casse mixte
            ['abcdefghijkl', 2, 'Correct'], // 12 caractères sans casse mixte
            ['Abcdefghijkl', 3, 'Bon'], // 12 caractères + casse mixte
            ['Abcdefgh1!', 3, 'Bon'], // casse + chiffre/symbole mais < 12
            ['Abcdefghijk1!', 4, 'Excellent'], // les quatre critères réunis
        ];

        for (const [password, score, label] of cases) {
            const { strength } = usePasswordStrength(ref(password));

            expect(strength.value.score, password).toBe(score);
            expect(strength.value.label, password).toBe(label);
        }
    });

    it('exige chiffre ET symbole pour le quatrième critère', () => {
        expect(usePasswordStrength(ref('Abcdefghij1')).strength.value.score).toBe(2);
        expect(usePasswordStrength(ref('Abcdefghij!')).strength.value.score).toBe(2);
    });

    it('suit la saisie : la jauge est réactive', () => {
        const password = ref('');
        const { strength } = usePasswordStrength(password);

        expect(strength.value.score).toBe(0);

        password.value = 'Abcdefghijk1!';
        expect(strength.value).toEqual({
            score: 4,
            label: 'Excellent',
            color: 'var(--success)',
        });
    });
});

describe('usePasswordMatch', () => {
    it('ne signale pas d’erreur tant que la confirmation est vide', () => {
        const { match } = usePasswordMatch(ref('Secret123!'), ref(''));

        expect(match.value).toBe(true);
    });

    it('signale la non-correspondance puis la lève quand les saisies convergent', () => {
        const password = ref('Secret123!');
        const confirmation = ref('Secret123?');
        const { match } = usePasswordMatch(password, confirmation);

        expect(match.value).toBe(false);

        confirmation.value = 'Secret123!';
        expect(match.value).toBe(true);

        password.value = 'Nouveau123!';
        expect(match.value).toBe(false);
    });
});
