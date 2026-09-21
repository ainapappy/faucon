import { describe, expect, it } from 'vitest';
import { Webhook } from '@lucide/vue';
import {
    categoryPresentation,
    groupNodeTypesForPalette,
    nodeCategoryExamples,
    nodeCategoryOrder,
    triggerPresentation,
} from '@/lib/nodeCategories';
import type {
    NodeCategory,
    NodeTypeCatalog,
    NodeTypeDefinition,
} from '@/types';

/** Définition de catalogue minimale (category, label, description et icon sont les champs lus). */
function definition(
    type: string,
    category: NodeCategory,
    overrides: Partial<NodeTypeDefinition> = {},
): NodeTypeDefinition {
    return {
        type,
        category,
        label: type,
        description: '',
        icon: 'zap',
        input: false,
        outputs: [],
        fields: [],
        ...overrides,
    };
}

const catalog: NodeTypeCatalog = {
    'trigger.webhook': definition('trigger.webhook', 'trigger', {
        label: 'Webhook',
        icon: 'webhook',
    }),
    'trigger.schedule': definition('trigger.schedule', 'trigger'),
    'data.http': definition('data.http', 'data'),
    'logic.condition': definition('logic.condition', 'logic'),
    'ai.classification': definition('ai.classification', 'ai'),
    'action.email': definition('action.email', 'action', {
        label: 'E-mail',
    }),
};

describe('nodeCategoryOrder (contrat design-system, ordre validé CVD)', () => {
    it('fige l’ordre exact des cinq catégories de la maquette', () => {
        expect(nodeCategoryOrder.map((category) => category.id)).toEqual([
            'trigger',
            'data',
            'logic',
            'ai',
            'action',
        ]);
    });

    it('fige la correspondance catégorie → token --cat-N (Logique = 5, IA = 3, Actions = 4)', () => {
        expect(nodeCategoryOrder.map((category) => category.colorToken)).toEqual(
            [
                'var(--cat-1)',
                'var(--cat-2)',
                'var(--cat-5)',
                'var(--cat-3)',
                'var(--cat-4)',
            ],
        );
    });

    it('accorde colorClass au token et interdit tout doublon d’id, de libellé ou de token', () => {
        const ids = new Set<string>();
        const labels = new Set<string>();
        const tokens = new Set<string>();

        for (const category of nodeCategoryOrder) {
            expect(category.colorClass, category.id).toBe(
                category.colorToken.replace(/^var\(--(.+)\)$/, 'text-$1'),
            );

            ids.add(category.id);
            labels.add(category.label);
            tokens.add(category.colorToken);
        }

        expect(ids.size).toBe(5);
        expect(labels.size).toBe(5);
        expect(tokens.size).toBe(5);
    });

    it('associe toujours une icône et un libellé à la couleur (règle CVD : jamais la couleur seule)', () => {
        for (const category of nodeCategoryOrder) {
            expect(category.label, category.id).toBeTruthy();
            expect(category.icon, category.id).toBeTruthy();
        }
    });
});

describe('categoryPresentation', () => {
    it('renvoie la présentation de chaque catégorie connue', () => {
        for (const category of nodeCategoryOrder) {
            expect(categoryPresentation(category.id)).toBe(category);
        }
    });

    it('replie muet sur Déclencheurs pour tout id inconnu', () => {
        expect(categoryPresentation('inconnu' as NodeCategory)).toBe(
            nodeCategoryOrder[0],
        );
    });
});

describe('groupNodeTypesForPalette', () => {
    it('groupe le catalogue dans l’ordre de la maquette et supprime les catégories vides', () => {
        const groups = groupNodeTypesForPalette(catalog);

        expect(groups.map((group) => group.id)).toEqual([
            'trigger',
            'data',
            'logic',
            'ai',
            'action',
        ]);
        expect(groups[0].types.map((type) => type.type)).toEqual([
            'trigger.webhook',
            'trigger.schedule',
        ]);
    });

    it('filtre par recherche insensible à la casse et aux espaces sur le libellé', () => {
        const groups = groupNodeTypesForPalette(catalog, '  e-mail  ');

        expect(groups.map((group) => group.id)).toEqual(['action']);
        expect(groups[0].types.map((type) => type.type)).toEqual([
            'action.email',
        ]);
    });

    it('cherche aussi dans la description', () => {
        const catalogue: NodeTypeCatalog = {
            'action.email': definition('action.email', 'action', {
                description: 'Envoie un e-mail transactionnel',
            }),
        };

        expect(
            groupNodeTypesForPalette(catalogue, 'TRANSACTIONNEL').map(
                (group) => group.id,
            ),
        ).toEqual(['action']);
    });

    it('renvoie un tableau vide quand la recherche ne matche rien', () => {
        expect(groupNodeTypesForPalette(catalog, 'inexistant')).toEqual([]);
    });
});

describe('triggerPresentation', () => {
    it('replie sur « Aucun » quand le workflow n’a pas encore de déclencheur', () => {
        expect(triggerPresentation(catalog, null)).toEqual({
            label: 'Aucun',
            colorToken: 'var(--muted-foreground)',
            colorClass: 'text-muted-foreground',
            icon: null,
        });
    });

    it('replie aussi sur un type absent du catalogue, sans jamais lever', () => {
        expect(triggerPresentation(catalog, 'trigger.fantome')).toEqual(
            triggerPresentation(catalog, null),
        );
    });

    it('habille le déclencheur avec la catégorie ambre et l’icône du type catalogué', () => {
        const presentation = triggerPresentation(catalog, 'trigger.webhook');

        expect(presentation.label).toBe('Webhook');
        expect(presentation.colorToken).toBe('var(--cat-1)');
        expect(presentation.colorClass).toBe('text-cat-1');
        expect(presentation.icon).toBe(Webhook);
    });

    it('dérive la couleur de la catégorie de la définition (pas de --cat-1 en dur)', () => {
        expect(triggerPresentation(catalog, 'data.http').colorToken).toBe(
            'var(--cat-2)',
        );
    });
});

describe('nodeCategoryExamples', () => {
    it('couvre exactement les cinq catégories (aperçus Entrées/Sorties de l’inspecteur)', () => {
        expect(Object.keys(nodeCategoryExamples).sort()).toEqual([
            'action',
            'ai',
            'data',
            'logic',
            'trigger',
        ]);
    });

    it('fournit un exemple non vide pour chaque catégorie', () => {
        for (const example of Object.values(nodeCategoryExamples)) {
            expect(Object.keys(example).length).toBeGreaterThan(0);
        }
    });
});
