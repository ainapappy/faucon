export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

import type { LucideIcon } from '@lucide/vue';

export type Passkey = {
    id: number;
    name: string;
    lastUsedAt: string | null;
};

/** Variante du panneau de marque du layout auth (maquettes login / register). */
export type AuthBrandVariant = 'login' | 'register';

/** Point clé du panneau de marque : icône lucide + libellé (jamais la couleur seule). */
export type AuthBrandPoint = {
    icon: LucideIcon;
    text: string;
};

/** Évaluation de la force d'un mot de passe (score 0-4, libellé, token CSS). */
export type PasswordStrength = {
    score: number;
    label: string;
    color: string;
};
