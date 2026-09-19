<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Switch } from '@/components/ui/switch';
import { triggerPresentation } from '@/lib/nodeCategories';
import { edit } from '@/routes/workflows';
import type { NodeTypeCatalog, WorkflowListItem } from '@/types';

const props = defineProps<{
    workflows: WorkflowListItem[];
    nodeTypes: NodeTypeCatalog;
    busyIds?: Set<number>;
    canUpdateWorkflow?: boolean;
}>();

const emit = defineEmits<{
    toggle: [workflow: WorkflowListItem];
}>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const editUrl = (workflow: WorkflowListItem) =>
    edit({ current_team: teamSlug.value, workflow: workflow.id }).url;

const triggerLabel = (workflow: WorkflowListItem) =>
    triggerPresentation(props.nodeTypes, workflow.triggerType).label;

const triggerStyle = (workflow: WorkflowListItem) => {
    const presentation = triggerPresentation(
        props.nodeTypes,
        workflow.triggerType,
    );
    return {
        backgroundColor: `color-mix(in srgb, ${presentation.colorToken} 13%, transparent)`,
        color: presentation.colorToken,
    };
};

const triggerIcon = (workflow: WorkflowListItem) =>
    triggerPresentation(props.nodeTypes, workflow.triggerType).icon;
</script>

<template>
    <div class="bg-card rounded-lg border shadow-xs">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Workflow</TableHead>
                    <TableHead>Déclencheur</TableHead>
                    <TableHead>Nodes</TableHead>
                    <TableHead>Dernière exécution</TableHead>
                    <TableHead>Succès</TableHead>
                    <TableHead>État</TableHead>
                    <TableHead class="w-11" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="workflow in workflows"
                    :key="workflow.id"
                    :data-test="`workflow-row-${workflow.id}`"
                >
                    <TableCell>
                        <Link
                            :href="editUrl(workflow)"
                            class="flex items-center gap-2.5"
                        >
                            <span
                                class="flex h-7 w-7 flex-none items-center justify-center rounded-md"
                                :style="triggerStyle(workflow)"
                                :title="triggerLabel(workflow)"
                            >
                                <component
                                    :is="triggerIcon(workflow)"
                                    v-if="triggerIcon(workflow)"
                                    class="h-3.5 w-3.5"
                                />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[13.5px] font-semibold">
                                    {{ workflow.name }}
                                </p>
                                <p
                                    class="text-muted-foreground line-clamp-2 max-w-44 text-xs"
                                >
                                    {{ workflow.description ?? '—' }}
                                </p>
                            </div>
                        </Link>
                    </TableCell>
                    <TableCell>
                        <Badge variant="outline">{{
                            triggerLabel(workflow)
                        }}</Badge>
                    </TableCell>
                    <TableCell class="text-muted-foreground">{{
                        workflow.nodesCount
                    }}</TableCell>
                    <TableCell class="text-muted-foreground">—</TableCell>
                    <TableCell class="text-muted-foreground">—</TableCell>
                    <TableCell @click.stop>
                        <Switch
                            :model-value="workflow.status === 'active'"
                            :disabled="
                                busyIds?.has(workflow.id) || !canUpdateWorkflow
                            "
                            :aria-label="`Activer le workflow ${workflow.name}`"
                            @update:model-value="emit('toggle', workflow)"
                        />
                    </TableCell>
                    <TableCell @click.stop>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground h-8 w-8"
                            as-child
                        >
                            <Link :href="editUrl(workflow)" aria-label="Éditer">
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
