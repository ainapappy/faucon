<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { groupNodeTypesForPalette } from '@/lib/nodeCategories';
import { nodeIcon } from '@/lib/nodeIcons';
import type { NodeTypeCatalog } from '@/types';

const props = defineProps<{
    open: boolean;
    catalog: NodeTypeCatalog;
}>();

const query = ref('');

/** Groupes de la palette, dans l'ordre fixe des catégories, filtrés par recherche. */
const groups = computed(() =>
    groupNodeTypesForPalette(props.catalog, query.value),
);

function onDragStart(event: DragEvent, type: string): void {
    event.dataTransfer?.setData('application/x-faucon-node', type);
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
    }
}
</script>

<template>
    <aside
        class="bg-card topbar-palette w-[246px] flex-none overflow-y-auto border-r p-3.5"
        :class="{ 'topbar-palette-hidden': !open }"
        data-test="node-palette"
    >
        <div class="relative mb-3">
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2"
            />
            <Input
                v-model="query"
                placeholder="Rechercher un node…"
                class="h-9 pl-8"
            />
        </div>

        <div v-for="group in groups" :key="group.id" class="mb-4">
            <p
                class="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold tracking-wider uppercase"
            >
                <span
                    class="h-2 w-2 flex-none rounded-[3px]"
                    :style="{ background: group.colorToken }"
                />
                {{ group.label }}
            </p>

            <button
                v-for="definition in group.types"
                :key="definition.type"
                type="button"
                class="hover:bg-accent hover:border-border group flex w-full cursor-grab items-center gap-2 rounded-md border border-transparent px-2.5 py-1.5 text-left text-[13px] transition-colors select-none active:cursor-grabbing"
                :title="definition.description"
                :data-test="`palette-item-${definition.type}`"
                draggable="true"
                @dragstart="onDragStart($event, definition.type)"
            >
                <component
                    :is="nodeIcon(definition.icon)"
                    class="text-muted-foreground h-[15px] w-[15px] flex-none"
                />
                <span class="truncate">{{ definition.label }}</span>
                <small
                    class="text-muted-foreground ml-auto text-[10.5px] opacity-0 transition-opacity group-hover:opacity-100"
                >
                    glisser
                </small>
            </button>
        </div>

        <p
            class="text-muted-foreground mt-1 border-t border-dashed px-1 pt-2 text-xs leading-relaxed"
        >
            Astuce : reliez une <b class="font-semibold">sortie</b> (rond droit)
            à une <b class="font-semibold">entrée</b> (rond gauche).
            <kbd class="bg-muted rounded border px-1 py-0.5 text-[10px]"
                >Suppr</kbd
            >
            supprime la sélection.
        </p>
    </aside>
</template>

<style scoped lang="scss">
/*
 * Repli de la palette (maquette : transition margin-left, cubic-bezier pop).
 */
.topbar-palette {
    transition: margin-left 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.topbar-palette-hidden {
    margin-left: -246px;
}
</style>
