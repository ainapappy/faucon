<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Layers, Search, Upload } from '@lucide/vue';
import { computed, toRef } from 'vue';
import Heading from '@/components/Heading.vue';
import TemplateCard from '@/components/templates/TemplateCard.vue';
import TemplateFilterChips from '@/components/templates/TemplateFilterChips.vue';
import { useTemplateGallery } from '@/composables/useTemplateGallery';
import {
    index as templatesIndex,
    use as useTemplateRoute,
} from '@/routes/templates';
import type { NodeTypeCatalog, WorkflowTemplateListItem } from '@/types';

type Props = {
    templates: WorkflowTemplateListItem[];
    nodeTypes: NodeTypeCatalog;
    permissions: {
        canCreateWorkflow: boolean;
        canUpdateWorkflow: boolean;
        canDeleteWorkflow: boolean;
    };
};

const props = defineProps<Props>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

defineOptions({
    layout: (pageProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Templates',
                href: templatesIndex({
                    current_team: pageProps.currentTeam?.slug ?? '',
                }).url,
            },
        ],
    }),
});

/*
 * Galerie : filtres et sections vivent dans useTemplateGallery ; l'action
 * « Utiliser » est une visite POST `templates.use` — le contrôleur redirige
 * vers l'éditeur du workflow créé et flashe le toast de succès.
 */
const gallery = useTemplateGallery({
    templates: toRef(() => props.templates),
    onUse: (template) =>
        router.visit(
            useTemplateRoute({
                current_team: teamSlug.value,
                template: template.id,
            }).url,
            { method: 'post' },
        ),
});

/** « Utiliser » sur la carte vedette (handler gardé : la vedette peut être null). */
function useFeatured(): void {
    if (gallery.featured.value) {
        void gallery.useTemplate(gallery.featured.value);
    }
}
</script>

<template>
    <Head title="Templates" />

    <div class="flex flex-col gap-4.5">
        <Heading
            title="Templates"
            description="Démarrez d'un modèle prêt à l'emploi — le graphe est dupliqué dans votre équipe, tout est éditable."
        />

        <div class="flex flex-col gap-2.5">
            <TemplateFilterChips
                v-model="gallery.origin.value"
                :chips="gallery.originChips.value"
                data-test="template-origin-chips"
            />
            <TemplateFilterChips
                v-model="gallery.category.value"
                :chips="gallery.categoryChips.value"
                data-test="template-category-chips"
            />
        </div>

        <!-- Galerie vide -->
        <div
            v-if="templates.length === 0"
            class="flex flex-col items-center gap-2.5 rounded-lg border border-dashed px-6 py-14 text-center"
            data-test="templates-empty"
        >
            <span
                class="bg-brand-soft text-brand-ink flex h-11 w-11 items-center justify-center rounded-lg"
            >
                <Layers class="h-5.5 w-5.5" />
            </span>
            <p class="font-semibold">Aucun template disponible</p>
            <p class="text-muted-foreground max-w-[42ch] text-sm">
                Les templates système arrivent avec l'installation — revenez
                plus tard.
            </p>
        </div>

        <!-- Aucun résultat pour la catégorie filtrée -->
        <div
            v-else-if="
                gallery.category.value !== 'all' && !gallery.hasResults.value
            "
            class="flex flex-col items-center gap-2.5 rounded-lg border border-dashed px-6 py-14 text-center"
            data-test="templates-empty-category"
        >
            <span
                class="bg-brand-soft text-brand-ink flex h-11 w-11 items-center justify-center rounded-lg"
            >
                <Search class="h-5.5 w-5.5" />
            </span>
            <p class="font-semibold">Aucun template dans cette catégorie</p>
            <p class="text-muted-foreground max-w-[42ch] text-sm">
                Essayez une autre catégorie, ou revenez à « Tous ».
            </p>
        </div>

        <!-- Sections empilées : Système, puis Mon équipe -->
        <template v-else>
            <section
                v-for="section in gallery.sections.value"
                :key="section.key"
                class="flex flex-col gap-3"
            >
                <div class="flex items-baseline gap-2">
                    <h2 class="text-[15px] font-semibold">
                        {{ section.label }}
                    </h2>
                    <span class="text-muted-foreground text-xs">
                        {{ section.total }}
                    </span>
                </div>

                <div
                    v-if="
                        section.items.length > 0 ||
                        (section.key === 'system' && gallery.showFeatured.value)
                    "
                    class="grid grid-cols-[repeat(auto-fill,minmax(290px,1fr))] gap-3.5"
                    :data-test="`templates-grid-${section.key}`"
                >
                    <TemplateCard
                        v-if="
                            section.key === 'system' &&
                            gallery.showFeatured.value &&
                            gallery.featured.value
                        "
                        :template="gallery.featured.value"
                        :node-types="nodeTypes"
                        :can-use="permissions.canCreateWorkflow"
                        :using-id="gallery.usingId.value"
                        featured
                        @use="useFeatured"
                    />

                    <TemplateCard
                        v-for="template in section.items"
                        :key="template.id"
                        :template="template"
                        :node-types="nodeTypes"
                        :can-use="permissions.canCreateWorkflow"
                        :using-id="gallery.usingId.value"
                        @use="gallery.useTemplate(template)"
                    />
                </div>

                <!-- Section « Mon équipe » vide : invitation à publier -->
                <div
                    v-else-if="
                        section.key === 'team' &&
                        gallery.category.value === 'all'
                    "
                    class="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-9 text-center"
                    data-test="templates-empty-team"
                >
                    <span
                        class="bg-brand-soft text-brand-ink flex h-9 w-9 items-center justify-center rounded-lg"
                    >
                        <Upload class="h-4.5 w-4.5" />
                    </span>
                    <p class="font-semibold">Aucun template d'équipe</p>
                    <p class="text-muted-foreground max-w-[46ch] text-sm">
                        Publiez un workflow comme template depuis sa liste ou
                        l'éditeur — il apparaîtra ici pour toute l'équipe.
                    </p>
                </div>

                <p
                    v-else-if="section.key === 'team'"
                    class="text-muted-foreground text-sm"
                >
                    Aucun template d'équipe dans cette catégorie.
                </p>
            </section>
        </template>
    </div>
</template>
