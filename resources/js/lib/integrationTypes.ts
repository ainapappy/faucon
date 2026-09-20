/*
 * Présentation et schéma de formulaire des intégrations (phase 5, lot F).
 *
 * Miroir documenté de la validation backend `app/Http/Requests/Integrations/`
 * (§2.10 du plan phase 5) : mêmes bornes, mêmes clés d'erreur
 * (`credentials.<champ>`) et MÊMES messages FR, apostrophes typographiques
 * comprises — toute erreur saisie est corrigée avant l'aller-retour, la 422
 * serveur reste le filet.
 *
 * Aucun secret ne transit ici : ce module ne manipule que des chaînes de
 * formulaire destinées à être POSTées, jamais re-présentées après stockage.
 */
import type { IntegrationType } from '@/types';

export type IntegrationMode = 'create' | 'edit';

export type IntegrationAuthMode = 'none' | 'bearer' | 'basic' | 'header';

export type SmtpEncryption = 'tls' | 'ssl' | 'none';

/** Options du sélecteur « Fournisseur » (U2 : deux types en phase 5). */
export const INTEGRATION_TYPE_OPTIONS: ReadonlyArray<{
    value: IntegrationType;
    label: string;
}> = [
    { value: 'generic_http', label: 'HTTP générique' },
    { value: 'smtp', label: 'SMTP / E-mail' },
];

/** Options du sélecteur « Authentification » (generic_http). */
export const AUTH_OPTIONS: ReadonlyArray<{
    value: IntegrationAuthMode;
    label: string;
}> = [
    { value: 'none', label: 'Aucune' },
    { value: 'bearer', label: 'Bearer token' },
    { value: 'basic', label: 'Basic' },
    { value: 'header', label: 'En-tête personnalisé' },
];

/** Options du sélecteur « Chiffrement » (smtp). */
export const ENCRYPTION_OPTIONS: ReadonlyArray<{
    value: SmtpEncryption;
    label: string;
}> = [
    { value: 'tls', label: 'TLS (démarré)' },
    { value: 'ssl', label: 'SSL implicite' },
    { value: 'none', label: 'Aucun' },
];

/** Bornes de validation — miroir exact des règles backend (§2.10). */
export const INTEGRATION_LIMITS = {
    name: 255,
    baseUrl: 2048,
    bearerToken: 1024,
    basicUsername: 255,
    basicPassword: 1024,
    headerName: 128,
    headerValue: 2000,
    smtpHost: 255,
    smtpUsername: 255,
    smtpPassword: 1024,
    smtpFrom: 255,
    portMin: 1,
    portMax: 65535,
} as const;

/**
 * Présentation d'un type d'intégration (maquette `typeStyle`) : lettre de
 * l'avatar + libellé FR + classes de l'avatar. La couleur (--cat-2 / --cat-4)
 * ne porte jamais l'information seule : la lettre et le libellé la doublent.
 */
export type IntegrationTypePresentation = {
    label: string;
    letter: string;
    avatarClass: string;
};

export function integrationTypePresentation(
    type: IntegrationType,
): IntegrationTypePresentation {
    return type === 'smtp'
        ? {
              label: 'SMTP / E-mail',
              letter: 'M',
              avatarClass: 'bg-cat-4/15 text-cat-4',
          }
        : {
              label: 'HTTP générique',
              letter: 'H',
              avatarClass: 'bg-cat-2/15 text-cat-2',
          };
}

/**
 * Ligne méta non secrète de la carte (maquette `cred-key`) : la méta réelle
 * servie par le backend (`meta` = baseUrl pour generic_http, host:port pour
 * smtp — jamais un secret). Absente ou vide : repli propre sur une
 * description générique de ce qui est stocké.
 */
export function integrationMetaLabel(
    meta: string | null,
    type: IntegrationType,
): string {
    const trimmed = meta?.trim() ?? '';

    if (trimmed !== '') {
        return trimmed;
    }

    return type === 'smtp'
        ? 'Serveur SMTP et identifiants enregistrés'
        : 'URL de base et authentification enregistrées';
}

/** Statut du dernier test de connexion (badge de la carte). */
export type TestStatus = 'success' | 'failed' | 'untested';

export function testStatus(succeeded: boolean | null): TestStatus {
    if (succeeded === null) {
        return 'untested';
    }

    return succeeded ? 'success' : 'failed';
}

export type TestStatusPresentation = {
    label: string;
    /** Classes du badge statut — tokens success/danger, jamais la couleur seule (dot + libellé). */
    badgeClass: string;
    showDot: boolean;
};

export function testStatusPresentation(
    status: TestStatus,
): TestStatusPresentation {
    switch (status) {
        case 'success':
            return {
                label: 'Connectée',
                badgeClass: 'bg-success-soft text-success',
                showDot: true,
            };
        case 'failed':
            return {
                label: 'Échec',
                badgeClass: 'bg-danger-soft text-danger',
                showDot: false,
            };
        default:
            return {
                label: 'Non testée',
                badgeClass: 'bg-secondary text-secondary-foreground',
                showDot: false,
            };
    }
}

/** Forme du dialog création/édition — tous les champs credentials y vivent, filtrés au payload. */
export type IntegrationFormShape = {
    name: string;
    type: IntegrationType;
    credentials: {
        baseUrl: string;
        auth: IntegrationAuthMode;
        token: string;
        username: string;
        password: string;
        headerName: string;
        headerValue: string;
        host: string;
        /** Saisi comme chaîne (input number), converti en entier au payload. */
        port: string;
        encryption: SmtpEncryption;
        from: string;
    };
};

export function emptyIntegrationForm(
    type: IntegrationType = 'generic_http',
): IntegrationFormShape {
    return {
        name: '',
        type,
        credentials: {
            baseUrl: '',
            auth: 'none',
            token: '',
            username: '',
            password: '',
            headerName: '',
            headerValue: '',
            host: '',
            port: '',
            encryption: 'tls',
            from: '',
        },
    };
}

/**
 * Vrai dès qu'au moins un champ credentials a été rempli (ou écarté de sa
 * valeur par défaut). En édition, des credentials entièrement vides valent
 * « inchangés » (absents du payload) — miroir de `UpdateIntegrationRequest`.
 */
export function hasCredentialInput(form: IntegrationFormShape): boolean {
    const c = form.credentials;

    if (form.type === 'generic_http') {
        return (
            c.baseUrl.trim() !== '' ||
            c.auth !== 'none' ||
            c.token.trim() !== '' ||
            c.username.trim() !== '' ||
            c.password.trim() !== '' ||
            c.headerName.trim() !== '' ||
            c.headerValue.trim() !== ''
        );
    }

    return (
        c.host.trim() !== '' ||
        c.port.trim() !== '' ||
        c.username.trim() !== '' ||
        c.password.trim() !== '' ||
        c.from.trim() !== '' ||
        c.encryption !== 'tls'
    );
}

/** Erreur de dépassement — même gabarit que le backend (`requireString`/`optionalString`). */
function maxError(field: string, max: number): string {
    return `Le champ « ${field} » ne doit pas dépasser ${max} caractères.`;
}

/**
 * Validation live du formulaire — miroir exact de `IntegrationRequest` (§2.10).
 *
 * En édition, des credentials entièrement vides ne sont pas validés (ils ne
 * partiront pas) ; dès qu'un champ est rempli, le jeu complet est exigé, comme
 * le backend qui remplace les credentials d'un bloc.
 *
 * @returns erreurs adressées par clé (`name`, `credentials.baseUrl`, …).
 */
export function validateIntegrationForm(
    form: IntegrationFormShape,
    mode: IntegrationMode,
): Record<string, string> {
    const errors: Record<string, string> = {};
    const validateCredentials = mode === 'create' || hasCredentialInput(form);

    const name = form.name.trim();

    if (name === '') {
        errors.name = 'Le nom est requis.';
    } else if (name.length > INTEGRATION_LIMITS.name) {
        errors.name = maxError('name', INTEGRATION_LIMITS.name);
    }

    if (!validateCredentials) {
        return errors;
    }

    if (form.type === 'generic_http') {
        validateGenericHttp(errors, form.credentials);
    } else {
        validateSmtp(errors, form.credentials);
    }

    return errors;
}

function validateGenericHttp(
    errors: Record<string, string>,
    c: IntegrationFormShape['credentials'],
): void {
    const baseUrl = c.baseUrl.trim();

    if (baseUrl === '') {
        errors['credentials.baseUrl'] = 'L’URL de base est requise.';
    } else {
        if (!/^https?:\/\//.test(baseUrl)) {
            errors['credentials.baseUrl'] =
                'L’URL de base doit commencer par http(s)://.';
        }

        if (baseUrl.length > INTEGRATION_LIMITS.baseUrl) {
            errors['credentials.baseUrl'] =
                'L’URL de base ne doit pas dépasser 2048 caractères.';
        }
    }

    if (!['none', 'bearer', 'basic', 'header'].includes(c.auth)) {
        errors['credentials.auth'] = 'Le mode d’authentification est invalide.';
    } else if (c.auth === 'bearer') {
        const token = c.token.trim();

        if (token === '') {
            errors['credentials.token'] =
                'Le jeton est requis pour l’authentification Bearer.';
        } else if (token.length > INTEGRATION_LIMITS.bearerToken) {
            errors['credentials.token'] = maxError(
                'token',
                INTEGRATION_LIMITS.bearerToken,
            );
        }
    } else if (c.auth === 'basic') {
        const username = c.username.trim();
        const password = c.password.trim();

        if (username === '') {
            errors['credentials.username'] =
                'Le nom d’utilisateur est requis pour l’authentification Basic.';
        } else if (username.length > INTEGRATION_LIMITS.basicUsername) {
            errors['credentials.username'] = maxError(
                'username',
                INTEGRATION_LIMITS.basicUsername,
            );
        }

        if (password === '') {
            errors['credentials.password'] =
                'Le mot de passe est requis pour l’authentification Basic.';
        } else if (password.length > INTEGRATION_LIMITS.basicPassword) {
            errors['credentials.password'] = maxError(
                'password',
                INTEGRATION_LIMITS.basicPassword,
            );
        }
    } else if (c.auth === 'header') {
        const headerName = c.headerName.trim();

        if (headerName === '') {
            errors['credentials.headerName'] = 'Le nom d’en-tête est requis.';
        } else {
            if (headerName.length > INTEGRATION_LIMITS.headerName) {
                errors['credentials.headerName'] =
                    'Le nom d’en-tête ne doit pas dépasser 128 caractères.';
            }

            if (!/^[A-Za-z0-9-]+$/.test(headerName)) {
                errors['credentials.headerName'] =
                    'Le nom d’en-tête ne doit contenir que des lettres, des chiffres et des tirets.';
            }
        }

        const headerValue = c.headerValue.trim();

        if (headerValue === '') {
            errors['credentials.headerValue'] =
                'La valeur d’en-tête est requise.';
        } else if (headerValue.length > INTEGRATION_LIMITS.headerValue) {
            errors['credentials.headerValue'] = maxError(
                'headerValue',
                INTEGRATION_LIMITS.headerValue,
            );
        }
    }
}

function validateSmtp(
    errors: Record<string, string>,
    c: IntegrationFormShape['credentials'],
): void {
    const host = c.host.trim();

    if (host === '') {
        errors['credentials.host'] = 'L’hôte SMTP est requis.';
    } else {
        if (host.includes('://')) {
            errors['credentials.host'] =
                'L’hôte SMTP ne doit pas contenir de schéma.';
        }

        if (host.length > INTEGRATION_LIMITS.smtpHost) {
            errors['credentials.host'] =
                'L’hôte SMTP ne doit pas dépasser 255 caractères.';
        }
    }

    const port = c.port.trim();

    if (
        !/^\d+$/.test(port) ||
        Number(port) < INTEGRATION_LIMITS.portMin ||
        Number(port) > INTEGRATION_LIMITS.portMax
    ) {
        errors['credentials.port'] =
            'Le port doit être un nombre entre 1 et 65535.';
    }

    if (!['tls', 'ssl', 'none'].includes(c.encryption)) {
        errors['credentials.encryption'] = 'Le chiffrement est invalide.';
    }

    const username = c.username.trim();

    if (username.length > INTEGRATION_LIMITS.smtpUsername) {
        errors['credentials.username'] = maxError(
            'username',
            INTEGRATION_LIMITS.smtpUsername,
        );
    }

    const password = c.password.trim();

    if (password.length > INTEGRATION_LIMITS.smtpPassword) {
        errors['credentials.password'] = maxError(
            'password',
            INTEGRATION_LIMITS.smtpPassword,
        );
    }

    const from = c.from.trim();

    if (from !== '') {
        if (from.length > INTEGRATION_LIMITS.smtpFrom) {
            errors['credentials.from'] =
                'L’adresse expéditeur ne doit pas dépasser 255 caractères.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(from)) {
            errors['credentials.from'] =
                'L’adresse expéditeur n’est pas valide.';
        }
    }
}

/**
 * Credentials prêts à poster — ne contient QUE les champs du mode d'authent
 * (aucun champ vide d'un autre mode ne pollue le stockage chiffré).
 * Le `type` est requis par le backend même en update : il est renvoyé par
 * `integrationFormPayload`, jamais perdu.
 */
export function credentialsPayload(
    form: IntegrationFormShape,
): Record<string, unknown> {
    const c = form.credentials;

    if (form.type === 'generic_http') {
        const credentials: Record<string, unknown> = {
            baseUrl: c.baseUrl.trim(),
            auth: c.auth,
        };

        if (c.auth === 'bearer') {
            credentials.token = c.token.trim();
        } else if (c.auth === 'basic') {
            credentials.username = c.username.trim();
            credentials.password = c.password.trim();
        } else if (c.auth === 'header') {
            credentials.headerName = c.headerName.trim();
            credentials.headerValue = c.headerValue.trim();
        }

        return credentials;
    }

    const credentials: Record<string, unknown> = {
        host: c.host.trim(),
        port: Number(c.port.trim()),
        encryption: c.encryption,
    };

    if (c.username.trim() !== '') {
        credentials.username = c.username.trim();
    }

    if (c.password.trim() !== '') {
        credentials.password = c.password.trim();
    }

    if (c.from.trim() !== '') {
        credentials.from = c.from.trim();
    }

    return credentials;
}

/**
 * Payload complet de la requête : credentials toujours présents en création,
 * absents en édition tant qu'aucun champ n'a été saisi (inchangés côté back).
 */
export function integrationFormPayload(
    form: IntegrationFormShape,
    mode: IntegrationMode,
): {
    name: string;
    type: IntegrationType;
    credentials?: Record<string, unknown>;
} {
    const payload: {
        name: string;
        type: IntegrationType;
        credentials?: Record<string, unknown>;
    } = {
        name: form.name.trim(),
        type: form.type,
    };

    if (mode === 'create' || hasCredentialInput(form)) {
        payload.credentials = credentialsPayload(form);
    }

    return payload;
}
