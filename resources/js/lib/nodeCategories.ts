/*
 * Présentation des cinq catégories de nodes — ordre fixe validé CVD.
 *
 * SEULE exception au rule « aucune id de type de node en dur côté front » :
 * les identifiants de CATÉGORIES (`trigger | data | logic | ai | action`)
 * sont figés par le design system (tokens `--cat-1…5`, ordre immuable).
 * Les types de nodes, eux, viennent toujours du catalogue servi en props.
 *
 * Règle CVD liante : la couleur ne porte jamais l'information seule —
 * chaque catégorie expose aussi une icône et un libellé.
 */
import type { Component } from 'vue';
import {
    Bot,
    Database,
    GitBranch,
    Send,
    Zap,
    type LucideProps,
} from '@lucide/vue';
import { nodeIcon } from '@/lib/nodeIcons';
import type {
    NodeCategory,
    NodeTypeCatalog,
    NodeTypeDefinition,
} from '@/types';

export type NodeCategoryPresentation = {
    id: NodeCategory;
    label: string;
    /** Token CSS `--cat-N` (valeur de couleur résolue par le thème). */
    colorToken: string;
    /** Utilitaire Tailwind exposé par `@theme inline` (`--color-cat-N`). */
    colorClass: string;
    icon: Component<LucideProps>;
};

/** Ordre immuable de la maquette : Déclencheurs, Données, Logique, IA, Actions. */
export const nodeCategoryOrder: readonly NodeCategoryPresentation[] = [
    {
        id: 'trigger',
        label: 'Déclencheurs',
        colorToken: 'var(--cat-1)',
        colorClass: 'text-cat-1',
        icon: Zap,
    },
    {
        id: 'data',
        label: 'Données',
        colorToken: 'var(--cat-2)',
        colorClass: 'text-cat-2',
        icon: Database,
    },
    {
        id: 'logic',
        label: 'Logique',
        colorToken: 'var(--cat-5)',
        colorClass: 'text-cat-5',
        icon: GitBranch,
    },
    {
        id: 'ai',
        label: 'IA',
        colorToken: 'var(--cat-3)',
        colorClass: 'text-cat-3',
        icon: Bot,
    },
    {
        id: 'action',
        label: 'Actions',
        colorToken: 'var(--cat-4)',
        colorClass: 'text-cat-4',
        icon: Send,
    },
];

const categoryById = new Map(
    nodeCategoryOrder.map((category) => [category.id, category]),
);

/** Présentation d'une catégorie ; repli muet sur la première pour un id inconnu. */
export function categoryPresentation(
    category: NodeCategory,
): NodeCategoryPresentation {
    return categoryById.get(category) ?? nodeCategoryOrder[0];
}

/** Groupe de la palette du builder : une catégorie + ses types du catalogue. */
export type NodePaletteGroup = NodeCategoryPresentation & {
    types: NodeTypeDefinition[];
};

/** Regroupe le catalogue par catégorie dans l'ordre de la maquette, en filtrant par recherche. */
export function groupNodeTypesForPalette(
    catalog: NodeTypeCatalog,
    query = '',
): NodePaletteGroup[] {
    const needle = query.trim().toLowerCase();

    return nodeCategoryOrder
        .map((category) => ({
            ...category,
            types: Object.values(catalog)
                .filter((definition) => definition.category === category.id)
                .filter(
                    (definition) =>
                        !needle ||
                        definition.label.toLowerCase().includes(needle) ||
                        definition.description.toLowerCase().includes(needle),
                ),
        }))
        .filter((group) => group.types.length > 0);
}

/** Repli quand un workflow n'a pas encore de node déclencheur. */
export const emptyTriggerPresentation = {
    label: 'Aucun',
    colorToken: 'var(--muted-foreground)',
    colorClass: 'text-muted-foreground',
    icon: null,
} satisfies {
    label: string;
    colorToken: string;
    colorClass: string;
    icon: Component<LucideProps> | null;
};

/**
 * Présentation du déclencheur d'un workflow de la liste : catégorie ambre
 * (`--cat-1`) + icône et libellé du type catalogué (jamais la couleur seule).
 */
export function triggerPresentation(
    catalog: NodeTypeCatalog,
    triggerType: string | null,
): {
    label: string;
    colorToken: string;
    colorClass: string;
    icon: Component<LucideProps> | null;
} {
    if (!triggerType) {
        return emptyTriggerPresentation;
    }

    const definition: NodeTypeDefinition | undefined = catalog[triggerType];

    if (!definition) {
        return emptyTriggerPresentation;
    }

    const category = categoryPresentation(definition.category);

    return {
        label: definition.label,
        colorToken: category.colorToken,
        colorClass: category.colorClass,
        icon: nodeIcon(definition.icon),
    };
}

/**
 * Exemples JSON « aperçu simulé » des onglets Entrées / Sorties de l'inspecteur,
 * portés de la maquette builder (portés PAR CATÉGORIE — jamais par type).
 */
export const nodeCategoryExamples: Record<
    NodeCategory,
    Record<string, unknown>
> = {
    trigger: {
        event: 'lead.received',
        payload: {
            email: 'client@exemple.com',
            message: 'Bonjour, je veux une démo…',
        },
    },
    data: { status: 200, body: { items: 3 } },
    logic: { branch: 'true', evaluated: '{{ ai.label }} == "lead"' },
    ai: { label: 'lead', confidence: 0.94, tokens: { input: 412, output: 58 } },
    action: { delivered: true, id: 'msg_01J9…' },
};
