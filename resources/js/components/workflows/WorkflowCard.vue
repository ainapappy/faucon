<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CircleCheck,
    Clock,
    Copy,
    Ellipsis,
    GitBranch,
    Play,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { triggerPresentation } from '@/lib/nodeCategories';
import { edit, duplicate } from '@/routes/workflows';
import type { NodeTypeCatalog, WorkflowListItem } from '@/types';

const props = defineProps<{
    workflow: WorkflowListItem;
    nodeTypes: NodeTypeCatalog;
    busy?: boolean;
    canUpdateWorkflow?: boolean;
    canDeleteWorkflow?: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
    run: [];
    remove: [];
}>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const editUrl = computed(
    () =>
        edit({ current_team: teamSlug.value, workflow: props.workflow.id }).url,
);

const duplicateUrl = computed(
    () =>
        duplicate({ current_team: teamSlug.value, workflow: props.workflow.id })
            .url,
);

const trigger = computed(() =>
    triggerPresentation(props.nodeTypes, props.workflow.triggerType),
);

const isActive = computed(() => props.workflow.status === 'active');
const statusLabel = computed(() => (isActive.value ? 'Actif' : 'En pause'));

const iconStyle = computed(() => ({
    backgroundColor: `color-mix(in srgb, ${trigger.value.colorToken} 13%, transparent)`,
    color: trigger.value.colorToken,
}));
</script>

<template>
    <article
        class="bg-card hover:border-ring/40 flex flex-col gap-3 rounded-lg border p-[18px] shadow-xs transition-all hover:shadow-md"
        :data-test="`workflow-card-${workflow.id}`"
    >
        <div class="flex items-start gap-3">
            <span
                class="flex h-[38px] w-[38px] flex-none items-center justify-center rounded-md"
                :style="iconStyle"
                :title="trigger.label"
            >
                <component
                    :is="trigger.icon"
                    v-if="trigger.icon"
                    class="h-[17px] w-[17px]"
                />
                <GitBranch v-else class="h-[17px] w-[17px]" />
            </span>

            <div class="flex min-w-0 flex-1 items-center gap-2">
                <h3
                    class="truncate text-sm font-semibold"
                    :title="workflow.name"
                >
                    {{ workflow.name }}
                </h3>
                <span
                    class="h-2 w-2 flex-none rounded-full"
                    :class="
                        isActive
                            ? 'bg-success animate-pulse-dot'
                            : 'bg-muted-foreground'
                    "
                    :title="statusLabel"
                />
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground h-8 w-8"
                        :aria-label="`Actions — ${workflow.name}`"
                        data-test="workflow-actions"
                    >
                        <Ellipsis class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52">
                    <DropdownMenuItem as-child>
                        <Link :href="editUrl" class="flex items-center gap-2">
                            <GitBranch class="h-4 w-4" /> Ouvrir l'éditeur
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem :disabled="busy" @select="emit('run')">
                        <Play class="h-4 w-4" /> Exécuter maintenant
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="canUpdateWorkflow"
                        as-child
                        :disabled="busy"
                    >
                        <Link
                            :href="duplicateUrl"
                            method="post"
                            class="flex items-center gap-2"
                        >
                            <Copy class="h-4 w-4" /> Dupliquer
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-if="canDeleteWorkflow"
                        variant="destructive"
                        :disabled="busy"
                        @select="emit('remove')"
                    >
                        <Trash2 class="h-4 w-4" /> Supprimer
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <p
            class="text-muted-foreground line-clamp-2 text-[13px] leading-relaxed"
        >
            {{
                workflow.description ??
                'Aucune description — décrivez ce workflow dans l’éditeur.'
            }}
        </p>

        <div
            class="text-muted-foreground flex flex-wrap items-center gap-3.5 text-xs"
        >
            <span class="inline-flex items-center gap-1.5">
                <GitBranch class="h-3 w-3" /> {{ workflow.nodesCount }} nodes
            </span>
            <span class="inline-flex items-center gap-1.5">
                <Clock class="h-3 w-3" /> Dernière exécution —
            </span>
            <span class="inline-flex items-center gap-1.5">
                <CircleCheck class="h-3 w-3" /> Succès —
            </span>
        </div>

        <div class="mt-auto flex items-center justify-between border-t pt-3">
            <div class="text-muted-foreground flex items-center gap-2 text-xs">
                <Switch
                    :model-value="isActive"
                    :disabled="busy || !canUpdateWorkflow"
                    :aria-label="`Activer le workflow ${workflow.name}`"
                    data-test="workflow-status-switch"
                    @update:model-value="emit('toggle')"
                />
                {{ statusLabel }}
            </div>

            <Link
                :href="editUrl"
                class="hover:bg-accent hover:text-accent-foreground inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition-colors"
            >
                Ouvrir <ArrowUpRight class="h-3.5 w-3.5" />
            </Link>
        </div>
    </article>
</template>

<style scoped>
/*
 * Pulsation douce du point « Actif » (maquette .status-dot.on) —
 * animée en SCSS car tw-animate-css n'expose pas ce keyframe.
 */
.animate-pulse-dot {
    animation: wf-pulse-dot 2.2s ease-out infinite;
}

@keyframes wf-pulse-dot {
    50% {
        box-shadow: 0 0 0 4px
            color-mix(in srgb, var(--success) 25%, transparent);
    }
}
</style>
