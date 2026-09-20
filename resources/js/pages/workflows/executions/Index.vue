<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    CircleSlash,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import ExecutionStatusBadge from '@/components/executions/ExecutionStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useExecutionPolling } from '@/composables/useExecutionPolling';
import {
    formatDurationMs,
    formatExecutionDate,
    formatTrigger,
} from '@/lib/executionFormat';
import {
    buildJournal,
    buildTimeline,
    durationBarWidth,
} from '@/lib/executionLogs';
import runWorkflow from '@/actions/App/Http/Controllers/Workflows/RunWorkflowController';
import cancelExecution from '@/actions/App/Http/Controllers/Workflows/WorkflowExecution/CancelWorkflowExecutionController';
import { index as executionsIndex } from '@/routes/workflow-executions';
import type {
    ExecutionFilters,
    PaginatedExecutions,
    WorkflowExecutionDetail,
    WorkflowExecutionListItem,
    WorkflowExecutionLogLevel,
    WorkflowExecutionNodeLogStatus,
    WorkflowExecutionStatus,
    WorkflowOption,
} from '@/types';

const props = defineProps<{
    executions: PaginatedExecutions;
    execution: WorkflowExecutionDetail | null;
    workflows: WorkflowOption[];
    filters: ExecutionFilters;
    logs_retention_days: number;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

defineOptions({
    layout: (layoutProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Exécutions',
                href: executionsIndex({
                    current_team: layoutProps.currentTeam?.slug ?? '',
                }).url,
            },
        ],
    }),
});

/* Filtres — copie locale des filtres serveur, appliqués à la navigation. */
const query = ref(props.filters.q);
const workflowId = ref(props.filters.workflow_id);
const status = ref(props.filters.status);

watch(
    () => props.filters,
    (next) => {
        query.value = next.q;
        workflowId.value = next.workflow_id;
        status.value = next.status;
    },
);

const STATUS_OPTIONS: { value: WorkflowExecutionStatus; label: string }[] = [
    { value: 'pending', label: 'En file' },
    { value: 'running', label: 'En cours' },
    { value: 'completed', label: 'Succès' },
    { value: 'failed', label: 'Échec' },
    { value: 'cancelled', label: 'Annulée' },
];

function buildIndexUrl(
    extra: Record<string, string | number | null | undefined>,
): string {
    const search: Record<string, string | number> = {};

    if (query.value.trim() !== '') {
        search.q = query.value.trim();
    }

    if (workflowId.value) {
        search.workflow_id = workflowId.value;
    }

    if (status.value) {
        search.status = status.value;
    }

    for (const [key, value] of Object.entries(extra)) {
        if (value !== null && value !== undefined && value !== '') {
            search[key] = value;
        }
    }

    return executionsIndex({ current_team: teamSlug.value }, { query: search })
        .url;
}

function applyFilters(): void {
    router.get(
        buildIndexUrl({}),
        {},
        {
            only: ['executions', 'filters'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function goToPage(target: number): void {
    router.get(
        buildIndexUrl({ page: target }),
        {},
        { only: ['executions'], preserveState: true, preserveScroll: true },
    );
}

/*
 * Exécution sélectionnée (sheet de détail, décision A1) : la source de
 * vérité est la prop `execution` — le polling la recharge en partiel.
 */
const selected = ref<WorkflowExecutionDetail | null>(props.execution);

watch(
    () => props.execution,
    (next) => {
        selected.value = next;
    },
);

const { polling } = useExecutionPolling({
    status: computed(() => selected.value?.status ?? null),
});

function open(execution: WorkflowExecutionListItem): void {
    router.get(
        buildIndexUrl({ execution: execution.id }),
        {},
        { only: ['execution'], preserveState: true, preserveScroll: true },
    );
}

function close(): void {
    selected.value = null;
    openStep.value = null;

    // Nettoie le deep-link et réaligne la page (le reload complet rend
    // `execution` à null, la sheet se referme).
    router.get(buildIndexUrl({}), {}, { preserveScroll: true });
}

/*
 * Timeline et journal (phase 8) : dérivés de la prop `execution.logs` par
 * `lib/executionLogs` — rows de node de la tentative courante pour la
 * timeline, toutes les rows messageées pour le journal.
 */
const openStep = ref<number | null>(null);

const timeline = computed(() =>
    selected.value ? buildTimeline(selected.value) : [],
);

const journal = computed(() =>
    selected.value ? buildJournal(selected.value) : [],
);

const maxStepMs = computed(() =>
    Math.max(1, ...timeline.value.map((node) => node.durationMs ?? 0)),
);

const cancelRequested = ref(false);

function cancel(): void {
    if (!selected.value) {
        return;
    }

    cancelRequested.value = true;

    router.post(
        cancelExecution({
            current_team: teamSlug.value,
            execution: selected.value.id,
        }).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.info('Annulation demandée', {
                    description: 'L’exécution s’arrêtera entre deux nodes.',
                });
            },
            onError: () => {
                cancelRequested.value = false;
                toast.error('Annulation impossible', {
                    description: 'Réessayez dans un instant.',
                });
            },
            onFinish: () => {
                cancelRequested.value = false;
            },
        },
    );
}

function retry(): void {
    if (!selected.value) {
        return;
    }

    const target = selected.value;

    router.post(
        runWorkflow({
            current_team: teamSlug.value,
            workflow: target.workflow.id,
        }).url,
        { input: (target.input ?? {}) as Record<string, FormDataConvertible> },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Exécution relancée`, {
                    description: `${target.workflow.name} — nouvelle exécution lancée.`,
                    action: {
                        label: 'Voir',
                        onClick: () => {
                            router.get(
                                buildIndexUrl({}),
                                {},
                                { preserveScroll: true },
                            );
                        },
                    },
                });
            },
            onError: (errors) => {
                toast.error('Relance impossible', {
                    description: Object.values(errors).join(' ') || undefined,
                });
            },
        },
    );
}

/*
 * Présentation des statuts de node du journal (règle CVD : la couleur ne
 * porte jamais l'information seule — icône/libellé systématiques). Dots en
 * teinte soft miroir de la maquette (step-dot.success/error/running).
 */
function stepDotClass(nodeStatus: WorkflowExecutionNodeLogStatus): string {
    return {
        queued: 'bg-info-soft text-info',
        ok: 'bg-success-soft text-success',
        error: 'bg-danger-soft text-danger',
        skipped: 'bg-muted text-muted-foreground',
    }[nodeStatus];
}

function stepBarClass(nodeStatus: WorkflowExecutionNodeLogStatus): string {
    return {
        queued: 'bg-info/70',
        ok: 'bg-success/70',
        error: 'bg-danger/70',
        skipped: 'bg-muted-foreground/30',
    }[nodeStatus];
}

/* Teinte d'une ligne de journal par niveau (miroir json-str/json-num). */
function journalLineClass(level: WorkflowExecutionLogLevel): string {
    return {
        info: 'text-muted-foreground',
        ok: 'text-success',
        error: 'text-danger',
    }[level];
}

function formatJson(value: unknown): string {
    return JSON.stringify(value ?? {}, null, 2);
}
</script>

<template>
    <Head title="Exécutions" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <Heading
            title="Exécutions"
            description="Historique des runs de vos workflows, en direct."
        />

        <!-- Toolbar : recherche + workflow + statut (maquette executions.html). -->
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center"
            role="search"
        >
            <div class="relative flex-1 sm:max-w-xs">
                <Search
                    class="text-muted-foreground absolute top-2.5 left-2.5 size-4"
                />
                <Input
                    v-model="query"
                    class="pl-8"
                    type="search"
                    placeholder="Rechercher par ID ou workflow…"
                    aria-label="Rechercher une exécution"
                    @keydown.enter="applyFilters"
                    @change="applyFilters"
                />
            </div>

            <Select
                :model-value="workflowId ? String(workflowId) : 'all'"
                @update:model-value="
                    (value) => {
                        const raw = String(value);
                        workflowId = raw === 'all' ? null : Number(raw);
                        applyFilters();
                    }
                "
            >
                <SelectTrigger
                    class="w-full sm:w-56"
                    aria-label="Filtrer par workflow"
                >
                    <SelectValue placeholder="Tous les workflows" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tous les workflows</SelectItem>
                    <SelectItem
                        v-for="workflow in workflows"
                        :key="workflow.id"
                        :value="String(workflow.id)"
                    >
                        {{ workflow.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="status ?? 'all'"
                @update:model-value="
                    (value) => {
                        const raw = String(value);
                        status =
                            raw === 'all'
                                ? null
                                : (raw as WorkflowExecutionStatus);
                        applyFilters();
                    }
                "
            >
                <SelectTrigger
                    class="w-full sm:w-44"
                    aria-label="Filtrer par statut"
                >
                    <SelectValue placeholder="Tous les statuts" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tous les statuts</SelectItem>
                    <SelectItem
                        v-for="option in STATUS_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Historique paginé. -->
        <div class="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/50">
                        <TableHead class="w-28">Statut</TableHead>
                        <TableHead>Workflow</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Déclencheur</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Tentative</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >Durée</TableHead
                        >
                        <TableHead class="hidden md:table-cell">Date</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in executions.data"
                        :key="item.id"
                        class="cursor-pointer"
                        :class="{
                            'bg-accent/60': selected?.id === item.id,
                        }"
                        @click="open(item)"
                    >
                        <TableCell>
                            <ExecutionStatusBadge :status="item.status" />
                        </TableCell>
                        <TableCell class="font-medium">
                            {{ item.workflow.name }}
                            <span
                                class="text-muted-foreground ml-2 font-mono text-xs"
                                >#{{ item.id }}</span
                            >
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            {{ formatTrigger(item.triggered_by) }}
                        </TableCell>
                        <TableCell class="hidden lg:table-cell">
                            {{ item.attempt }}
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            {{ formatDurationMs(item.duration_ms) }}
                        </TableCell>
                        <TableCell
                            class="text-muted-foreground hidden md:table-cell"
                        >
                            {{ formatExecutionDate(item.created_at) }}
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="executions.data.length === 0">
                        <TableCell
                            colspan="6"
                            class="text-muted-foreground h-32 text-center"
                        >
                            Aucune exécution ne correspond à ces filtres.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination. -->
        <div
            v-if="executions.last_page > 1"
            class="flex items-center justify-between"
        >
            <p class="text-muted-foreground text-sm">
                Page {{ executions.current_page }} sur
                {{ executions.last_page }} — {{ executions.total }} exécutions
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="executions.current_page <= 1"
                    @click="goToPage(executions.current_page - 1)"
                >
                    <ChevronLeft class="size-4" />
                    Précédente
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="executions.current_page >= executions.last_page"
                    @click="goToPage(executions.current_page + 1)"
                >
                    Suivante
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>

        <!-- Sheet de détail (maquette : panneau latéral « Détail de l'exécution »). -->
        <Sheet
            :open="selected !== null"
            @update:open="
                (value: boolean) => {
                    if (!value) close();
                }
            "
        >
            <SheetContent
                class="flex flex-col gap-0 overflow-hidden sm:max-w-lg"
            >
                <SheetHeader v-if="selected" class="space-y-1 text-left">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm"
                            >#{{ selected.id }}</span
                        >
                        <ExecutionStatusBadge :status="selected.status" />
                        <span
                            v-if="polling"
                            class="text-muted-foreground text-xs"
                            >mise à jour en direct…</span
                        >
                    </div>
                    <SheetTitle class="text-base font-semibold">
                        {{ selected.workflow.name }}
                    </SheetTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="absolute top-3 right-3"
                        aria-label="Fermer"
                        @click="close()"
                    >
                        <X class="size-4" />
                    </Button>
                </SheetHeader>

                <div
                    v-if="selected"
                    class="flex-1 space-y-5 overflow-y-auto px-4 pb-4"
                >
                    <!-- Résumé (maquette : grille déclencheur / démarrée / durée / tentative). -->
                    <dl class="grid grid-cols-2 gap-3">
                        <div>
                            <dt class="text-muted-foreground text-xs">
                                Déclencheur
                            </dt>
                            <dd class="text-sm font-semibold">
                                {{ formatTrigger(selected.triggered_by) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">
                                Démarrée
                            </dt>
                            <dd class="text-sm font-semibold">
                                {{ formatExecutionDate(selected.started_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">
                                Durée totale
                            </dt>
                            <dd class="text-sm font-semibold">
                                {{
                                    selected.duration_ms
                                        ? formatDurationMs(selected.duration_ms)
                                        : 'en cours…'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">
                                Tentative
                            </dt>
                            <dd class="text-sm font-semibold">
                                {{ selected.attempt }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Alertes par statut (maquette + A5 : pending / cancelled). -->
                    <Alert v-if="selected.status === 'completed'">
                        <AlertTitle>Terminée avec succès</AlertTitle>
                        <AlertDescription>
                            Tous les nodes ont répondu dans les délais.
                        </AlertDescription>
                    </Alert>
                    <Alert
                        v-else-if="selected.status === 'failed'"
                        variant="destructive"
                    >
                        <AlertTitle>
                            Échec{{
                                selected.error?.type
                                    ? ` — ${selected.error.type}`
                                    : ''
                            }}
                        </AlertTitle>
                        <AlertDescription>
                            {{
                                selected.error?.message ??
                                'Une erreur est survenue.'
                            }}
                        </AlertDescription>
                    </Alert>
                    <Alert v-else-if="selected.status === 'running'">
                        <AlertTitle>Exécution en cours</AlertTitle>
                        <AlertDescription>
                            Cette vue se met à jour automatiquement.
                        </AlertDescription>
                    </Alert>
                    <Alert v-else-if="selected.status === 'pending'">
                        <AlertTitle>En attente d'exécution</AlertTitle>
                        <AlertDescription>
                            Le run est en file — il démarre dès qu'un worker est
                            disponible.
                        </AlertDescription>
                    </Alert>
                    <Alert v-else-if="selected.status === 'cancelled'">
                        <AlertTitle>Exécution annulée</AlertTitle>
                        <AlertDescription>
                            Arrêtée entre deux nodes à la demande d'un membre de
                            l'équipe.
                        </AlertDescription>
                    </Alert>

                    <!-- Timeline des nodes. -->
                    <div>
                        <p class="mb-2 text-sm font-semibold">
                            Parcours par node
                        </p>

                        <p
                            v-if="timeline.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            Le parcours s'affichera dès le démarrage du run.
                        </p>

                        <ol v-else class="space-y-3">
                            <li
                                v-for="(node, indexStep) in timeline"
                                :key="node.nodeKey"
                                class="flex gap-3"
                            >
                                <span
                                    class="mt-1 flex size-5 shrink-0 items-center justify-center rounded-full"
                                    :class="stepDotClass(node.status)"
                                >
                                    <Spinner
                                        v-if="node.status === 'queued'"
                                        class="size-3"
                                    />
                                    <Check
                                        v-else-if="node.status === 'ok'"
                                        class="size-3"
                                    />
                                    <X
                                        v-else-if="node.status === 'error'"
                                        class="size-3"
                                    />
                                    <span
                                        v-else
                                        class="text-[10px] leading-none"
                                        >·</span
                                    >
                                </span>
                                <div class="min-w-0 flex-1">
                                    <button
                                        class="flex w-full items-baseline justify-between gap-2 text-left"
                                        type="button"
                                        @click="
                                            openStep =
                                                openStep === indexStep
                                                    ? null
                                                    : indexStep
                                        "
                                    >
                                        <span
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ node.nodeName }}
                                            <Badge
                                                variant="outline"
                                                class="ml-2 font-mono text-[10px]"
                                                >{{ node.nodeType }}</Badge
                                            >
                                        </span>
                                        <time
                                            class="text-muted-foreground shrink-0 text-xs"
                                        >
                                            {{
                                                formatDurationMs(
                                                    node.durationMs,
                                                )
                                            }}
                                        </time>
                                    </button>
                                    <!-- Barre proportionnelle : seulement les nodes mesurés (ok/error). -->
                                    <div
                                        v-if="node.durationMs !== null"
                                        class="bg-muted mt-1.5 h-1 overflow-hidden rounded-full"
                                    >
                                        <i
                                            class="block h-full rounded-full"
                                            :class="stepBarClass(node.status)"
                                            :style="{
                                                width: durationBarWidth(
                                                    node.durationMs,
                                                    maxStepMs,
                                                ),
                                            }"
                                        />
                                    </div>
                                    <!-- Dépliage : entrée / sortie (payloads déjà masqués côté back) + erreur. -->
                                    <div
                                        v-if="openStep === indexStep"
                                        class="mt-2 space-y-2"
                                    >
                                        <div>
                                            <p
                                                class="text-muted-foreground mb-1 text-[10px] font-semibold tracking-wider uppercase"
                                            >
                                                Entrée
                                            </p>
                                            <pre
                                                class="bg-muted/60 overflow-x-auto rounded-lg p-2 font-mono text-xs"
                                                >{{
                                                    formatJson(node.input)
                                                }}</pre>
                                        </div>
                                        <div>
                                            <p
                                                class="text-muted-foreground mb-1 text-[10px] font-semibold tracking-wider uppercase"
                                            >
                                                Sortie
                                            </p>
                                            <pre
                                                class="bg-muted/60 overflow-x-auto rounded-lg p-2 font-mono text-xs"
                                                >{{
                                                    formatJson(node.output)
                                                }}</pre>
                                        </div>
                                        <div v-if="node.error">
                                            <p
                                                class="text-danger mb-1 text-[10px] font-semibold tracking-wider uppercase"
                                            >
                                                Erreur
                                            </p>
                                            <pre
                                                class="bg-danger-soft overflow-x-auto rounded-lg p-2 font-mono text-xs"
                                                >{{
                                                    formatJson(node.error)
                                                }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <!-- Journal (phase 8 : rows horodatées servies par execution.logs). -->
                    <div>
                        <p class="mb-2 text-sm font-semibold">Journal</p>

                        <p
                            v-if="journal.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            Aucune entrée de journal pour cette exécution.
                        </p>

                        <div
                            v-else
                            class="bg-muted/60 space-y-1 rounded-lg p-3 font-mono text-xs"
                        >
                            <template
                                v-for="(item, index) in journal"
                                :key="
                                    item.kind === 'separator'
                                        ? `separator-${item.attempt}-${index}`
                                        : item.logId
                                "
                            >
                                <div
                                    v-if="item.kind === 'separator'"
                                    class="flex items-center gap-2 py-1"
                                >
                                    <span class="bg-border h-px flex-1" />
                                    <span
                                        class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase"
                                        >Tentative {{ item.attempt }}</span
                                    >
                                    <span class="bg-border h-px flex-1" />
                                </div>
                                <div v-else class="flex gap-2.5">
                                    <span
                                        class="text-muted-foreground shrink-0 tabular-nums"
                                        >{{ item.t }}</span
                                    >
                                    <span
                                        class="min-w-0 wrap-break-word"
                                        :class="journalLineClass(item.level)"
                                        >{{ item.message }}</span
                                    >
                                </div>
                            </template>
                        </div>

                        <p class="text-muted-foreground mt-3 text-xs">
                            Rétention : {{ logs_retention_days }} jours · les
                            secrets sont masqués automatiquement dans les
                            entrées / sorties.
                        </p>
                    </div>
                </div>

                <!-- Footer contextuel (maquette + A5). -->
                <SheetFooter
                    v-if="selected"
                    class="flex-row justify-end gap-2 border-t"
                >
                    <Button variant="ghost" @click="close()">Fermer</Button>
                    <Button
                        v-if="selected.status === 'failed'"
                        @click="retry()"
                    >
                        <RotateCcw class="size-4" />
                        Relancer l'exécution
                    </Button>
                    <Button
                        v-else-if="
                            selected.status === 'pending' ||
                            selected.status === 'running'
                        "
                        variant="destructive"
                        :disabled="cancelRequested"
                        @click="cancel()"
                    >
                        <CircleSlash class="size-4" />
                        Annuler l'exécution
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </div>
</template>
