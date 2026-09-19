<script setup lang="ts">
import { List, LayoutGrid, Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    WorkflowListFilter,
    WorkflowListSort,
    WorkflowListView,
} from '@/composables/useWorkflowList';

const query = defineModel<string>('query', { required: true });
const filter = defineModel<WorkflowListFilter>('filter', { required: true });
const sort = defineModel<WorkflowListSort>('sort', { required: true });
const view = defineModel<WorkflowListView>('view', { required: true });

defineProps<{
    counts: Record<WorkflowListFilter, number>;
}>();

const filters: Array<{ value: WorkflowListFilter; label: string }> = [
    { value: 'all', label: 'Tous' },
    { value: 'active', label: 'Actifs' },
    { value: 'paused', label: 'En pause' },
];
</script>

<template>
    <div class="mb-[18px] flex flex-wrap items-center gap-2.5">
        <div class="relative w-full max-w-[340px] min-w-50 flex-1">
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
            />
            <Input
                v-model="query"
                placeholder="Rechercher un workflow…"
                class="pl-9"
                data-test="workflow-search"
            />
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <button
                v-for="item in filters"
                :key="item.value"
                type="button"
                class="hover:border-input hover:text-foreground inline-flex h-[30px] items-center gap-1.5 rounded-full border px-3.5 text-xs font-medium transition-colors"
                :class="
                    filter === item.value
                        ? 'bg-foreground text-background border-foreground'
                        : 'text-muted-foreground bg-background'
                "
                :data-test="`workflow-filter-${item.value}`"
                @click="filter = item.value"
            >
                {{ item.label }}
                <span class="text-muted-foreground">{{
                    counts[item.value]
                }}</span>
            </button>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <Select v-model="sort">
                <SelectTrigger
                    class="h-[34px] w-[150px]"
                    data-test="workflow-sort"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="recent">Plus récents</SelectItem>
                    <SelectItem value="name">Nom (A→Z)</SelectItem>
                    <SelectItem value="runs">Exécutions</SelectItem>
                </SelectContent>
            </Select>

            <div
                class="bg-background border-input flex items-center gap-0.5 rounded-md border p-0.5 shadow-xs"
            >
                <button
                    type="button"
                    class="hover:text-foreground inline-flex h-7 w-8 items-center justify-center rounded-sm transition-colors"
                    :class="
                        view === 'grid'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground'
                    "
                    aria-label="Vue grille"
                    @click="view = 'grid'"
                >
                    <LayoutGrid class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    class="hover:text-foreground inline-flex h-7 w-8 items-center justify-center rounded-sm transition-colors"
                    :class="
                        view === 'list'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground'
                    "
                    aria-label="Vue liste"
                    @click="view = 'list'"
                >
                    <List class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
