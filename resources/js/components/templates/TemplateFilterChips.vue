<script setup lang="ts">
import type { TemplateGalleryChip } from '@/composables/useTemplateGallery';
import { templateGalleryChipRowClass } from '@/composables/useTemplateGallery';

/*
 * Rangée de chips de filtre de la galerie (maquette templates.html) :
 * même idiome que les chips de la liste des workflows (pill active inversée,
 * compteur dérivé des données en muted). Réutilisée pour l'origine et la
 * catégorie — aucun calcul ici, les chips arrivent prêtes du composable.
 */
const model = defineModel<string>({ required: true });

defineProps<{
    chips: TemplateGalleryChip[];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5" role="group">
        <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            :class="templateGalleryChipRowClass(chip.key === model)"
            :data-test="`template-chip-${chip.key}`"
            @click="model = chip.key"
        >
            {{ chip.label }}
            <span class="text-muted-foreground">{{ chip.count }}</span>
        </button>
    </div>
</template>
