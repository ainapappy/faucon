<script setup lang="ts">
import { ArrowUpRight, Copy, GitBranch, LoaderCircle, Star } from '@lucide/vue';
import { computed } from 'vue';
import TemplateGraphPreview from '@/components/templates/TemplateGraphPreview.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { templateOriginLabel } from '@/composables/useTemplateGallery';
import type { NodeTypeCatalog, WorkflowTemplateListItem } from '@/types';

/*
 * Carte template de la galerie (maquette templates.html, D9) : aperçu SVG,
 * badge d'origine, description clampée, méta « N nodes » + badge catégorie
 * (données réelles — A2 : pas de compteur d'usage ni de note), bouton
 * « Utiliser ». Variante vedette (premier template système) : aperçu large,
 * badge « Recommandé », bouton primaire.
 */
const props = defineProps<{
    template: WorkflowTemplateListItem;
    nodeTypes: NodeTypeCatalog;
    /** Permission workflow:create — sans elle, le bouton est désactivé. */
    canUse?: boolean;
    /** Id du template en cours d'instanciation (état processing partagé). */
    usingId?: number | null;
    /** Carte vedette : `md:col-span-2`, aperçu large, bouton primaire. */
    featured?: boolean;
}>();

const emit = defineEmits<{
    use: [];
}>();

const USE_BUTTON_TITLE =
    'Vous n’avez pas la permission de créer des workflows dans cette équipe.';

const busy = computed(() => props.usingId != null);
const isActive = computed(() => props.usingId === props.template.id);
const useTitle = computed(() => (props.canUse ? undefined : USE_BUTTON_TITLE));
</script>

<template>
    <article
        class="bg-card hover:border-ring/40 flex flex-col overflow-hidden rounded-lg border shadow-xs transition-all hover:shadow-md"
        :class="featured ? 'md:col-span-2' : ''"
        :data-test="`template-card-${template.id}`"
    >
        <div class="tpl-preview flex items-center border-b p-[18px]">
            <div class="h-24 w-full" :class="featured ? 'sm:h-[134px]' : ''">
                <TemplateGraphPreview
                    :graph="template.graph"
                    :node-types="nodeTypes"
                />
            </div>
        </div>

        <div
            class="flex flex-1 flex-col gap-2"
            :class="featured ? 'p-5' : 'px-4 pt-3.5 pb-4'"
        >
            <div class="flex items-center gap-2">
                <h3
                    class="truncate font-semibold"
                    :class="featured ? 'text-base' : 'text-sm'"
                    :title="template.name"
                >
                    {{ template.name }}
                </h3>
                <span
                    v-if="featured"
                    class="bg-brand-soft text-brand-ink inline-flex flex-none items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] leading-4 font-medium"
                >
                    <Star class="h-3 w-3" /> Recommandé
                </span>
                <Badge variant="secondary" class="flex-none">
                    {{ templateOriginLabel(template.origin) }}
                </Badge>
            </div>

            <p
                class="text-muted-foreground leading-relaxed"
                :class="
                    featured
                        ? 'max-w-[58ch] text-[13.5px]'
                        : 'line-clamp-2 text-[12.5px]'
                "
            >
                {{ template.description ?? 'Aucune description.' }}
            </p>

            <div
                class="mt-auto flex items-center justify-between gap-2 border-t pt-2.5"
            >
                <div
                    class="text-muted-foreground flex items-center gap-2.5 text-[11.5px]"
                >
                    <span class="inline-flex items-center gap-1">
                        <GitBranch class="h-3 w-3" />
                        {{ template.nodesCount }} nodes
                    </span>
                    <Badge variant="secondary">{{ template.category }}</Badge>
                </div>

                <Button
                    v-if="featured"
                    size="sm"
                    :disabled="busy || !canUse"
                    :title="useTitle"
                    data-test="template-use"
                    @click="emit('use')"
                >
                    <LoaderCircle v-if="isActive" class="animate-spin" />
                    <Copy v-else class="h-3.5 w-3.5" />
                    {{ isActive ? 'Création…' : 'Utiliser ce template' }}
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    :disabled="busy || !canUse"
                    :title="useTitle"
                    data-test="template-use"
                    @click="emit('use')"
                >
                    <LoaderCircle v-if="isActive" class="animate-spin" />
                    {{ isActive ? 'Création…' : 'Utiliser' }}
                    <ArrowUpRight v-if="!isActive" class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    </article>
</template>

<style scoped>
/*
 * Fond de l'aperçu (maquette .tpl-preview) : halo de marque + mix muted/card,
 * exprimés en SCSS car color-mix imbriqué dans un dégradé est illisible en
 * utilitaires Tailwind.
 */
.tpl-preview {
    background:
        radial-gradient(
            circle at 30% 20%,
            color-mix(in srgb, var(--brand) 7%, transparent),
            transparent 55%
        ),
        color-mix(in srgb, var(--muted) 55%, var(--card));
}
</style>
