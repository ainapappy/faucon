/*
 * État de la galerie de templates (page templates/Index, phase 9 — D9).
 *
 * Filtres origine + catégorie (chips avec compteurs dérivés des données),
 * sections empilées Système / Mon équipe, carte vedette (premier template
 * système, seulement sur « Tous » + « Tous ») et état processing de
 * l'action « Utiliser » (id en vol → tous les boutons désactivés).
 * La visite POST `templates.use` elle-même est injectée : le composable
 * reste testable hors navigateur, la page câble Wayfinder.
 */
import type { Ref } from 'vue';
import { computed, ref } from 'vue';
import type { TemplateOrigin, WorkflowTemplateListItem } from '@/types';

/** Filtre d'origine : « Tous », ou une origine précise. */
export type TemplateGalleryOriginFilter = 'all' | TemplateOrigin;

/**
 * Filtre de catégorie : la valeur sentinelle « all » (toutes) ou une
 * catégorie libre dérivée des données (jamais une liste en dur).
 */
export type TemplateGalleryCategoryFilter = string;

/** Chip de filtre (origine ou catégorie) avec son compteur dérivé. */
export type TemplateGalleryChip = {
    key: string;
    label: string;
    count: number;
};

/** Section empilée de la galerie (une par origine visible). */
export type TemplateGallerySection = {
    key: Exclude<TemplateOrigin, never>;
    label: string;
    /** Total filtré, carte vedette incluse (le libellé compte juste). */
    total: number;
    /** Cartes à rendre, vedette exclue quand elle est rendue à part. */
    items: WorkflowTemplateListItem[];
};

/** Libellé FR du badge d'origine d'une carte. */
export function templateOriginLabel(origin: TemplateOrigin): string {
    return origin === 'system' ? 'Système' : 'Mon équipe';
}

/**
 * Classes d'une chip de filtre (idiome des chips de la liste des workflows :
 * pill active inversée foreground/background, inactive muted sur fond page).
 * Chaînes complètes et littérales : le scanner Tailwind les détecte.
 */
export function templateGalleryChipRowClass(active: boolean): string {
    return [
        'inline-flex h-[30px] items-center gap-1.5 rounded-full border px-3.5 text-xs font-medium transition-colors',
        active
            ? 'bg-foreground text-background border-foreground'
            : 'text-muted-foreground bg-background hover:border-input hover:text-foreground',
    ].join(' ');
}

export type UseTemplateGalleryOptions = {
    /** Templates servis en prop (système d'abord, ordre par id). */
    templates: Ref<WorkflowTemplateListItem[]>;
    /** Visite d'instanciation (POST `templates.use` câblé côté page). */
    onUse: (template: WorkflowTemplateListItem) => void | Promise<void>;
};

export type UseTemplateGalleryReturn = {
    origin: Ref<TemplateGalleryOriginFilter>;
    category: Ref<TemplateGalleryCategoryFilter>;
    /** Id du template en cours d'instanciation, null au repos. */
    usingId: Ref<number | null>;
    originChips: Ref<TemplateGalleryChip[]>;
    categoryChips: Ref<TemplateGalleryChip[]>;
    /** Premier template système (ordre id) — carte vedette, null s'il n'existe pas. */
    featured: Ref<WorkflowTemplateListItem | null>;
    /** La vedette est-elle affichée ? (origine « Tous » + catégorie « Tous ») */
    showFeatured: Ref<boolean>;
    sections: Ref<TemplateGallerySection[]>;
    /** Au moins une carte à rendre (vedette comprise) ? */
    hasResults: Ref<boolean>;
    useTemplate: (template: WorkflowTemplateListItem) => Promise<void>;
};

export function useTemplateGallery(
    options: UseTemplateGalleryOptions,
): UseTemplateGalleryReturn {
    const origin = ref<TemplateGalleryOriginFilter>('all');
    const category = ref<TemplateGalleryCategoryFilter>('all');
    const usingId = ref<number | null>(null);

    const originChips = computed<TemplateGalleryChip[]>(() => {
        const templates = options.templates.value;

        return [
            { key: 'all', label: 'Tous', count: templates.length },
            {
                key: 'system',
                label: 'Système',
                count: templates.filter((t) => t.origin === 'system').length,
            },
            {
                key: 'team',
                label: 'Mon équipe',
                count: templates.filter((t) => t.origin === 'team').length,
            },
        ];
    });

    const categoryChips = computed<TemplateGalleryChip[]>(() => {
        const templates = options.templates.value;
        const counts = new Map<string, number>();

        for (const template of templates) {
            counts.set(
                template.category,
                (counts.get(template.category) ?? 0) + 1,
            );
        }

        return [
            { key: 'all', label: 'Tous', count: templates.length },
            ...Array.from(counts, ([key, count]) => ({
                key,
                label: key,
                count,
            })),
        ];
    });

    const featured = computed<WorkflowTemplateListItem | null>(
        () =>
            options.templates.value.find(
                (template) => template.origin === 'system',
            ) ?? null,
    );

    const showFeatured = computed(
        () =>
            origin.value === 'all' &&
            category.value === 'all' &&
            featured.value !== null,
    );

    const sections = computed<TemplateGallerySection[]>(() => {
        const matchesCategory = (template: WorkflowTemplateListItem) =>
            category.value === 'all' || template.category === category.value;

        const system = options.templates.value.filter(
            (template) =>
                template.origin === 'system' && matchesCategory(template),
        );
        const team = options.templates.value.filter(
            (template) =>
                template.origin === 'team' && matchesCategory(template),
        );
        const systemItems =
            showFeatured.value && featured.value !== null
                ? system.filter(
                      (template) => template.id !== featured.value?.id,
                  )
                : system;

        const visibleSections: TemplateGallerySection[] = [];

        if (origin.value === 'all' || origin.value === 'system') {
            visibleSections.push({
                key: 'system',
                label: 'Templates système',
                total: system.length,
                items: systemItems,
            });
        }

        if (origin.value === 'all' || origin.value === 'team') {
            visibleSections.push({
                key: 'team',
                label: 'Mon équipe',
                total: team.length,
                items: team,
            });
        }

        return visibleSections;
    });

    const hasResults = computed(() =>
        sections.value.some(
            (section) =>
                section.items.length > 0 ||
                (section.key === 'system' && showFeatured.value),
        ),
    );

    /** Instancie un template ; un seul appel en vol, boutons désactivés pendant. */
    async function useTemplate(
        template: WorkflowTemplateListItem,
    ): Promise<void> {
        if (usingId.value !== null) {
            return;
        }

        usingId.value = template.id;
        try {
            await options.onUse(template);
        } finally {
            usingId.value = null;
        }
    }

    return {
        origin,
        category,
        usingId,
        originChips,
        categoryChips,
        featured,
        showFeatured,
        sections,
        hasResults,
        useTemplate,
    };
}
