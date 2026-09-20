<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { Plus, Upload, Workflow as WorkflowIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import CreateWorkflowDialog from '@/components/workflows/CreateWorkflowDialog.vue';
import DuplicateWorkflowDialog from '@/components/workflows/DuplicateWorkflowDialog.vue';
import PublishTemplateDialog from '@/components/workflows/PublishTemplateDialog.vue';
import WorkflowCard from '@/components/workflows/WorkflowCard.vue';
import WorkflowTable from '@/components/workflows/WorkflowTable.vue';
import WorkflowToolbar from '@/components/workflows/WorkflowToolbar.vue';
import { Button } from '@/components/ui/button';
import {
    useWorkflowList,
    type ToggleStatusResult,
} from '@/composables/useWorkflowList';
import { destroy, index, update } from '@/routes/workflows';
import { index as executionsIndexUrl } from '@/routes/workflow-executions';
import runWorkflowUrl from '@/actions/App/Http/Controllers/Workflows/RunWorkflowController';
import type {
    NodeTypeCatalog,
    WorkflowListItem,
    WorkflowStatus,
} from '@/types';

type Props = {
    workflows: WorkflowListItem[];
    nodeTypes: NodeTypeCatalog;
    /** Catégories distinctes des templates visibles — suggestions de publication. */
    templateCategories: string[];
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
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Workflows',
                href: index({ current_team: props.currentTeam?.slug ?? '' })
                    .url,
            },
        ],
    }),
});

// Copie locale : les bascules optimistes mutent cette liste, pas la prop.
const items = ref<WorkflowListItem[]>([...props.workflows]);

/*
 * PATCH silencieux du statut (useHttp, pas de visite Inertia) : la bascule
 * optimiste et le repli vivent dans useWorkflowList ; les toasts ici.
 * useHttp envoie l'état réactif de l'instance (`form.data()`).
 */
const statusHttp = useHttp<{ status: WorkflowStatus }>({ status: 'draft' });

async function toggleRequest(
    workflow: WorkflowListItem,
    status: WorkflowStatus,
): Promise<ToggleStatusResult> {
    let ok = false;

    statusHttp.status = status;
    await statusHttp
        .patch(
            update({ current_team: teamSlug.value, workflow: workflow.id }).url,
            {
                onSuccess: () => {
                    ok = true;
                    if (status === 'active') {
                        toast.success('Workflow activé', {
                            description: workflow.name,
                        });
                    } else {
                        toast.info('Workflow mis en pause', {
                            description: workflow.name,
                        });
                    }
                },
                onError: (errors) => {
                    toast.error('Statut non modifié', {
                        description: Object.values(errors).join(' '),
                    });
                },
                onHttpException: (response) => {
                    toast.error('Statut non modifié', {
                        description: `Erreur ${response.status} — réessayez.`,
                    });
                },
            },
        )
        .catch(() => {
            toast.error('Statut non modifié', {
                description: 'Erreur réseau.',
            });
        });

    return { ok };
}

const list = useWorkflowList({
    items,
    toggleRequest,
});

/** « Exécuter maintenant » → run en queue (phase 7) : toast de dispatch + lien vers l'exécution. */
function runNow(workflow: WorkflowListItem): void {
    const http = useHttp();

    http.post(
        runWorkflowUrl({ current_team: teamSlug.value, workflow: workflow.id })
            .url,
        {
            onSuccess: () => {
                toast.success(`Exécution lancée`, {
                    description: `${workflow.name} — suivez son avancement en direct.`,
                    action: {
                        label: 'Voir',
                        onClick: () => {
                            router.visit(
                                executionsIndexUrl({
                                    current_team: teamSlug.value,
                                }).url,
                            );
                        },
                    },
                });
            },
            onError: (errors) => {
                toast.error('Exécution impossible', {
                    description: Object.values(errors).join(' ') || undefined,
                });
            },
            onHttpException: (response) => {
                toast.error('Exécution impossible', {
                    description: `Erreur ${response.status} — réessayez.`,
                });
            },
        },
    ).catch(() => {
        toast.error('Exécution impossible', {
            description: 'Erreur réseau.',
        });
    });
}

/** Suppression : visite DELETE + flash toast servi par le contrôleur. */
function remove(workflow: WorkflowListItem): void {
    router.visit(
        destroy({ current_team: teamSlug.value, workflow: workflow.id }).url,
        {
            method: 'delete',
        },
    );
}

/* ---- Duplication (confirmation) et publication en template (phase 9) ---- */
const duplicatingWorkflow = ref<WorkflowListItem | null>(null);
const duplicateDialogOpen = ref(false);
const publishingWorkflow = ref<WorkflowListItem | null>(null);
const publishDialogOpen = ref(false);

function openDuplicate(workflow: WorkflowListItem): void {
    duplicatingWorkflow.value = workflow;
    duplicateDialogOpen.value = true;
}

function openPublish(workflow: WorkflowListItem): void {
    publishingWorkflow.value = workflow;
    publishDialogOpen.value = true;
}

/** Import JSON : informatif uniquement — l'import de fichiers n'est pas implémenté. */
function notifyImport(): void {
    toast.info('Import', {
        description:
            'Glissez un fichier JSON exporté — l’import arrive avec une prochaine phase.',
    });
}
</script>

<template>
    <Head title="Workflows" />

    <div class="flex flex-col gap-4.5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Workflows"
                :description="`${workflows.length} workflow${workflows.length > 1 ? 's' : ''} dans ${page.props.currentTeam?.name ?? 'votre équipe'} — activez, éditez ou dupliquez vos automatisations.`"
            />

            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    data-test="workflows-import"
                    @click="notifyImport"
                >
                    <Upload /> Importer
                </Button>

                <CreateWorkflowDialog v-if="permissions.canCreateWorkflow">
                    <Button data-test="workflows-create">
                        <Plus /> Nouveau workflow
                    </Button>
                </CreateWorkflowDialog>
            </div>
        </div>

        <WorkflowToolbar
            v-model:query="list.query.value"
            v-model:filter="list.filter.value"
            v-model:sort="list.sort.value"
            v-model:view="list.view.value"
            :counts="list.counts.value"
        />

        <div
            v-if="list.filtered.value.length === 0"
            class="bg-card flex flex-col items-center gap-2.5 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <span
                class="bg-brand-soft text-brand-ink flex h-11 w-11 items-center justify-center rounded-lg"
            >
                <WorkflowIcon class="h-5.5 w-5.5" />
            </span>
            <p class="font-semibold">Aucun workflow trouvé</p>
            <p class="text-muted-foreground max-w-[38ch] text-sm">
                Essayez une autre recherche, ou créez votre premier workflow à
                partir d'un template.
            </p>
            <CreateWorkflowDialog v-if="permissions.canCreateWorkflow">
                <Button size="sm" class="mt-1.5">
                    <Plus /> Créer un workflow
                </Button>
            </CreateWorkflowDialog>
        </div>

        <div
            v-else-if="list.view.value === 'grid'"
            class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-3.5"
        >
            <WorkflowCard
                v-for="workflow in list.filtered.value"
                :key="workflow.id"
                :workflow="workflow"
                :node-types="nodeTypes"
                :busy="list.togglingIds.value.has(workflow.id)"
                :can-update-workflow="permissions.canUpdateWorkflow"
                :can-delete-workflow="permissions.canDeleteWorkflow"
                @toggle="list.toggle(workflow)"
                @run="runNow(workflow)"
                @duplicate="openDuplicate(workflow)"
                @publish="openPublish(workflow)"
                @remove="remove(workflow)"
            />
        </div>

        <WorkflowTable
            v-else
            :workflows="list.filtered.value"
            :node-types="nodeTypes"
            :busy-ids="list.togglingIds.value"
            :can-update-workflow="permissions.canUpdateWorkflow"
            @toggle="list.toggle"
        />
    </div>

    <!-- Duplication : confirmation avant la visite POST (A3) ; toast servi par le contrôleur. -->
    <DuplicateWorkflowDialog
        :open="duplicateDialogOpen"
        :workflow="duplicatingWorkflow"
        @update:open="duplicateDialogOpen = $event"
    />

    <!-- Publication en template : suggestions = catégories des templates visibles (D11). -->
    <PublishTemplateDialog
        :open="publishDialogOpen"
        :workflow="publishingWorkflow"
        :categories="templateCategories"
        @update:open="publishDialogOpen = $event"
    />
</template>
