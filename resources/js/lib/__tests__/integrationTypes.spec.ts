import { describe, expect, it } from 'vitest';
import {
    AUTH_OPTIONS,
    ENCRYPTION_OPTIONS,
    INTEGRATION_LIMITS,
    INTEGRATION_TYPE_OPTIONS,
    credentialsPayload,
    emptyIntegrationForm,
    hasCredentialInput,
    integrationFormPayload,
    integrationMetaLabel,
    integrationTypePresentation,
    testStatus,
    testStatusPresentation,
    validateIntegrationForm,
} from '@/lib/integrationTypes';

const LONG_TEXT = 'x'.repeat(2100);

describe('options des sélecteurs (U2)', () => {
    it('propose exactement les deux fournisseurs de la phase 5', () => {
        expect(INTEGRATION_TYPE_OPTIONS).toEqual([
            { value: 'generic_http', label: 'HTTP générique' },
            { value: 'smtp', label: 'SMTP / E-mail' },
        ]);
    });

    it('propose les quatre modes d’authentification du backend', () => {
        expect(AUTH_OPTIONS.map((o) => o.value)).toEqual([
            'none',
            'bearer',
            'basic',
            'header',
        ]);
    });

    it('propose les trois chiffrements SMTP du backend', () => {
        expect(ENCRYPTION_OPTIONS.map((o) => o.value)).toEqual([
            'tls',
            'ssl',
            'none',
        ]);
    });
});

describe('présentation par type (maquette typeStyle)', () => {
    it('affiche la lettre H et le libellé HTTP générique pour generic_http', () => {
        const presentation = integrationTypePresentation('generic_http');

        expect(presentation.letter).toBe('H');
        expect(presentation.label).toBe('HTTP générique');
        expect(presentation.avatarClass).toContain('cat-2');
    });

    it('affiche la lettre M et le libellé SMTP / E-mail pour smtp', () => {
        const presentation = integrationTypePresentation('smtp');

        expect(presentation.letter).toBe('M');
        expect(presentation.label).toBe('SMTP / E-mail');
        expect(presentation.avatarClass).toContain('cat-4');
    });

    it('affiche la méta réelle du backend (baseUrl / host:port — jamais un secret)', () => {
        expect(
            integrationMetaLabel('https://api.exemple.com', 'generic_http'),
        ).toBe('https://api.exemple.com');
        expect(integrationMetaLabel('smtp.exemple.com:587', 'smtp')).toBe(
            'smtp.exemple.com:587',
        );
    });

    it('retombe sur la description générique quand la méta est absente ou vide', () => {
        expect(integrationMetaLabel(null, 'generic_http')).toBe(
            'URL de base et authentification enregistrées',
        );
        expect(integrationMetaLabel(null, 'smtp')).toBe(
            'Serveur SMTP et identifiants enregistrés',
        );
        expect(integrationMetaLabel('   ', 'smtp')).toBe(
            'Serveur SMTP et identifiants enregistrés',
        );
    });
});

describe('statut du dernier test (badge carte)', () => {
    it('mappe true / false / null sur Connectée / Échec / Non testée', () => {
        expect(testStatus(true)).toBe('success');
        expect(testStatus(false)).toBe('failed');
        expect(testStatus(null)).toBe('untested');
    });

    it('porte le libellé et le tone, dot seulement sur le succès', () => {
        expect(testStatusPresentation('success')).toEqual({
            label: 'Connectée',
            badgeClass: 'bg-success-soft text-success',
            showDot: true,
        });
        expect(testStatusPresentation('failed').label).toBe('Échec');
        expect(testStatusPresentation('failed').showDot).toBe(false);
        expect(testStatusPresentation('untested').label).toBe('Non testée');
        expect(testStatusPresentation('untested').showDot).toBe(false);
    });
});

describe('validation live — miroir du backend (§2.10)', () => {
    it('exige le nom et les credentials à la création', () => {
        const errors = validateIntegrationForm(
            emptyIntegrationForm(),
            'create',
        );

        expect(errors.name).toBe('Le nom est requis.');
        expect(errors['credentials.baseUrl']).toBe(
            'L’URL de base est requise.',
        );
    });

    it('borne le nom à 255 caractères', () => {
        const form = emptyIntegrationForm();
        form.name = 'x'.repeat(INTEGRATION_LIMITS.name + 1);

        const errors = validateIntegrationForm(form, 'create');

        expect(errors.name).toBe(
            'Le champ « name » ne doit pas dépasser 255 caractères.',
        );
    });

    it('exige un schéma http(s) pour l’URL de base', () => {
        const form = emptyIntegrationForm();
        form.name = 'API';
        form.credentials.baseUrl = 'ftp://api.exemple.com';

        const errors = validateIntegrationForm(form, 'create');

        expect(errors['credentials.baseUrl']).toBe(
            'L’URL de base doit commencer par http(s)://.',
        );
    });

    it('borne l’URL de base à 2048 caractères', () => {
        const form = emptyIntegrationForm();
        form.name = 'API';
        form.credentials.baseUrl = `https://api.exemple.com/${LONG_TEXT}`;

        const errors = validateIntegrationForm(form, 'create');

        expect(errors['credentials.baseUrl']).toBe(
            'L’URL de base ne doit pas dépasser 2048 caractères.',
        );
    });

    it('accepte une création generic_http auth none complète', () => {
        const form = emptyIntegrationForm();
        form.name = 'API';
        form.credentials.baseUrl = 'https://api.exemple.com';

        expect(validateIntegrationForm(form, 'create')).toEqual({});
    });

    it('exige le jeton pour l’authentification Bearer', () => {
        const form = emptyIntegrationForm();
        form.name = 'API';
        form.credentials.baseUrl = 'https://api.exemple.com';
        form.credentials.auth = 'bearer';

        const errors = validateIntegrationForm(form, 'create');

        expect(errors['credentials.token']).toBe(
            'Le jeton est requis pour l’authentification Bearer.',
        );
    });

    it('exige utilisateur et mot de passe pour Basic', () => {
        const form = emptyIntegrationForm();
        form.name = 'API';
        form.credentials.baseUrl = 'https://api.exemple.com';
        form.credentials.auth = 'basic';

        const errors = validateIntegrationForm(form, 'create');

        expect(errors['credentials.username']).toBe(
            'Le nom d’utilisateur est requis pour l’authentification Basic.',
        );
        expect(errors['credentials.password']).toBe(
            'Le mot de passe est requis pour l’authentification Basic.',
        );
    });

    it('valide le nom d’en-tête personnalisé (vide, longueur, alphabet)', () => {
        const base = emptyIntegrationForm();
        base.name = 'API';
        base.credentials.baseUrl = 'https://api.exemple.com';
        base.credentials.auth = 'header';

        const empty = validateIntegrationForm(base, 'create');
        expect(empty['credentials.headerName']).toBe(
            'Le nom d’en-tête est requis.',
        );
        expect(empty['credentials.headerValue']).toBe(
            'La valeur d’en-tête est requise.',
        );

        base.credentials.headerName = 'X Api Key!';
        const badCharset = validateIntegrationForm(base, 'create');
        expect(badCharset['credentials.headerName']).toBe(
            'Le nom d’en-tête ne doit contenir que des lettres, des chiffres et des tirets.',
        );

        base.credentials.headerName = 'X-Api-Key';
        base.credentials.headerValue = 'valeur';
        expect(validateIntegrationForm(base, 'create')).toEqual({});
    });

    it('exige hôte et port SMTP dans la plage 1..65535', () => {
        const form = emptyIntegrationForm('smtp');
        form.name = 'Mail';

        const emptyErrors = validateIntegrationForm(form, 'create');
        expect(emptyErrors['credentials.host']).toBe('L’hôte SMTP est requis.');
        expect(emptyErrors['credentials.port']).toBe(
            'Le port doit être un nombre entre 1 et 65535.',
        );

        for (const port of ['0', '70000', 'abc', '58.5']) {
            form.credentials.port = port;
            expect(
                validateIntegrationForm(form, 'create')['credentials.port'],
            ).toBe('Le port doit être un nombre entre 1 et 65535.');
        }

        form.credentials.port = '587';
        form.credentials.host = 'smtp://exemple.com';
        expect(
            validateIntegrationForm(form, 'create')['credentials.host'],
        ).toBe('L’hôte SMTP ne doit pas contenir de schéma.');

        form.credentials.host = 'smtp.exemple.com';
        expect(validateIntegrationForm(form, 'create')).toEqual({});
    });

    it('valide l’adresse expéditeur optionnelle', () => {
        const form = emptyIntegrationForm('smtp');
        form.name = 'Mail';
        form.credentials.host = 'smtp.exemple.com';
        form.credentials.port = '587';

        form.credentials.from = 'pas-un-email';
        expect(
            validateIntegrationForm(form, 'create')['credentials.from'],
        ).toBe('L’adresse expéditeur n’est pas valide.');

        form.credentials.from = 'no-reply@exemple.com';
        expect(validateIntegrationForm(form, 'create')).toEqual({});
    });

    it('en édition, des credentials entièrement vides ne sont pas validés (inchangés)', () => {
        const form = emptyIntegrationForm();
        form.name = 'Renommée seule';

        expect(validateIntegrationForm(form, 'edit')).toEqual({});
    });

    it('en édition, un champ credentials saisi exige le jeu complet', () => {
        const form = emptyIntegrationForm();
        form.name = 'Rotation';
        form.credentials.token = 'nouveau-jeton';

        const errors = validateIntegrationForm(form, 'edit');

        expect(errors['credentials.baseUrl']).toBe(
            'L’URL de base est requise.',
        );
    });
});

describe('payload de la requête', () => {
    it('détecte toute saisie de credentials (hasCredentialInput)', () => {
        const form = emptyIntegrationForm();
        expect(hasCredentialInput(form)).toBe(false);

        form.credentials.auth = 'none';
        expect(hasCredentialInput(form)).toBe(false);

        form.credentials.baseUrl = 'https://api.exemple.com';
        expect(hasCredentialInput(form)).toBe(true);

        const smtp = emptyIntegrationForm('smtp');
        expect(hasCredentialInput(smtp)).toBe(false);
        smtp.credentials.encryption = 'ssl';
        expect(hasCredentialInput(smtp)).toBe(true);
    });

    it('filtre les credentials aux champs du mode d’authentification', () => {
        const bearer = emptyIntegrationForm();
        bearer.credentials.baseUrl = 'https://api.exemple.com';
        bearer.credentials.auth = 'bearer';
        bearer.credentials.token = 'sk-123';
        bearer.credentials.headerName = 'résidu';

        expect(credentialsPayload(bearer)).toEqual({
            baseUrl: 'https://api.exemple.com',
            auth: 'bearer',
            token: 'sk-123',
        });

        const header = emptyIntegrationForm();
        header.credentials.baseUrl = 'https://api.exemple.com';
        header.credentials.auth = 'header';
        header.credentials.headerName = 'X-Api-Key';
        header.credentials.headerValue = 'valeur';

        expect(credentialsPayload(header)).toEqual({
            baseUrl: 'https://api.exemple.com',
            auth: 'header',
            headerName: 'X-Api-Key',
            headerValue: 'valeur',
        });
    });

    it('convertit le port SMTP en nombre et omet les champs optionnels vides', () => {
        const form = emptyIntegrationForm('smtp');
        form.credentials.host = 'smtp.exemple.com';
        form.credentials.port = '587';

        expect(credentialsPayload(form)).toEqual({
            host: 'smtp.exemple.com',
            port: 587,
            encryption: 'tls',
        });
    });

    it('inclut toujours les credentials en création', () => {
        const form = emptyIntegrationForm();
        form.name = 'API';
        form.credentials.baseUrl = 'https://api.exemple.com';

        expect(integrationFormPayload(form, 'create')).toEqual({
            name: 'API',
            type: 'generic_http',
            credentials: {
                baseUrl: 'https://api.exemple.com',
                auth: 'none',
            },
        });
    });

    it('omet les credentials en édition tant qu’aucun champ n’a été saisi', () => {
        const form = emptyIntegrationForm();
        form.name = 'Renommée';

        expect(integrationFormPayload(form, 'edit')).toEqual({
            name: 'Renommée',
            type: 'generic_http',
        });
    });

    it('renvoie le type même en édition avec credentials (exigé par le backend)', () => {
        const form = emptyIntegrationForm('smtp');
        form.name = 'Rotation';
        form.credentials.host = 'smtp.exemple.com';
        form.credentials.port = '465';
        form.credentials.encryption = 'ssl';

        expect(integrationFormPayload(form, 'edit')).toEqual({
            name: 'Rotation',
            type: 'smtp',
            credentials: {
                host: 'smtp.exemple.com',
                port: 465,
                encryption: 'ssl',
            },
        });
    });
});
