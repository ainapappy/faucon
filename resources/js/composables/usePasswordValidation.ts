import { computed, type ComputedRef, type Ref } from 'vue';
import type { PasswordStrength } from '@/types';

// Scoring repris de la maquette register (.knowledge/design/register.html) :
// longueur >= 8, >= 12, casse mixte, chiffre + symbole.
// Les couleurs référencent les tokens de resources/css/app.css.
const strengthLevels: PasswordStrength[] = [
    { score: 0, label: '—', color: 'var(--muted-foreground)' },
    { score: 1, label: 'Faible', color: 'var(--destructive)' },
    { score: 2, label: 'Correct', color: 'var(--warning)' },
    { score: 3, label: 'Bon', color: 'var(--brand-ink)' },
    { score: 4, label: 'Excellent', color: 'var(--success)' },
];

function scorePassword(password: string): number {
    if (!password) {
        return 0;
    }

    let score = 0;

    if (password.length >= 8) {
        score++;
    }

    if (password.length >= 12) {
        score++;
    }

    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) {
        score++;
    }

    if (/\d/.test(password) && /[^A-Za-z0-9]/.test(password)) {
        score++;
    }

    return score;
}

/** Jauge de force d'un mot de passe (4 segments + libellé de la maquette). */
export function usePasswordStrength(password: Ref<string>): {
    strength: ComputedRef<PasswordStrength>;
} {
    const strength = computed<PasswordStrength>(() => {
        const score = scorePassword(password.value);

        return { ...strengthLevels[score], score };
    });

    return { strength };
}

/** Correspondance entre le mot de passe et sa confirmation (maquette register). */
export function usePasswordMatch(
    password: Ref<string>,
    confirmation: Ref<string>,
): {
    match: ComputedRef<boolean>;
} {
    const match = computed(
        () => !confirmation.value || confirmation.value === password.value,
    );

    return { match };
}
