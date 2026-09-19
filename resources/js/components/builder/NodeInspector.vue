<script setup lang="ts">
import { Braces, Move, PanelRightClose, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { BuilderNode } from '@/composables/useWorkflowBuilder';
import { formatNodeOutput } from '@/composables/useWorkflowTestRun';
import {
    categoryPresentation,
    nodeCategoryExamples,
} from '@/lib/nodeCategories';
import { nodeIcon } from '@/lib/nodeIcons';
import type {
    NodeRunResult,
    NodeTypeDefinition,
    WorkflowStatus,
} from '@/types';

const props = defineProps<{
    open: boolean;
    node: BuilderNode | null;
    definition: NodeTypeDefinition | null;
    status: WorkflowStatus;
    canUpdateWorkflow?: boolean;
    /** Résultat réel du node sélectionné au dernier test (`null` = pas de run). */
    nodeResult: NodeRunResult | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    updateNodeName: [key: string, name: string];
    updateNodeConfig: [
        key: string,
        fieldKey: string,
        value: string | number | boolean,
    ];
    removeNode: [key: string];
    changeStatus: [status: WorkflowStatus];
}>();

const tab = ref<'settings' | 'in' | 'out'>('settings');

// Sélection par défaut sur l'onglet paramètres à chaque nouveau node sélectionné.
watch(
    () => props.node?.key,
    () => {
        tab.value = 'settings';
    },
);

const category = computed(() =>
    props.definition ? categoryPresentation(props.definition.category) : null,
);

const colorToken = computed(
    () => category.value?.colorToken ?? 'var(--muted-foreground)',
);

const configOf = (fieldKey: string): string | number => {
    const value = props.node?.config[fieldKey];
    return typeof value === 'boolean' || value === undefined
        ? ''
        : (value as string | number);
};

/*
 * Relais vers les emits avec garde sur le node courant — les expressions
 * inline du template ne peuvent pas restreindre `node` (vue-tsc).
 */
const renameNode = (name: string | number): void => {
    if (props.node) {
        emit('updateNodeName', props.node.key, String(name));
    }
};

const setField = (fieldKey: string, value: string | number): void => {
    if (props.node) {
        emit('updateNodeConfig', props.node.key, fieldKey, value);
    }
};

const setRangeField = (fieldKey: string, event: Event): void => {
    setField(fieldKey, Number((event.target as HTMLInputElement).value));
};

/*
 * Aperçus « entrées » : exemples de démonstration par catégorie (maquette),
 * explicitement étiquetés « aperçu simulé » — le moteur n'expose pas
 * l'input par node (limitation documentée du contrat phase 4).
 */
const previewJson = computed(() =>
    JSON.stringify(
        props.definition
            ? nodeCategoryExamples[props.definition.category]
            : { ok: true },
        null,
        2,
    ),
);

/*
 * Sortie réelle du node sélectionné au dernier test, rendue en TEXTE
 * (`formatNodeOutput`) — remplace l'aperçu simulé quand un run existe.
 */
const outputJson = computed(() =>
    props.nodeResult && props.nodeResult.status !== 'skipped'
        ? formatNodeOutput(props.nodeResult.output)
        : '',
);
</script>

<template>
    <aside
        class="bg-card w-[296px] flex-none overflow-y-auto border-l"
        :class="{ 'inspector-hidden': !open }"
        data-test="node-inspector"
    >
        <!-- Node sélectionné -->
        <template v-if="node">
            <div
                class="bg-card sticky top-0 z-2 flex items-center gap-2 border-b px-4 py-3.5"
            >
                <span
                    class="flex h-[30px] w-[30px] flex-none items-center justify-center rounded-md"
                    :style="{
                        background: `color-mix(in srgb, ${colorToken} 13%, transparent)`,
                        color: colorToken,
                    }"
                >
                    <component
                        :is="nodeIcon(definition?.icon)"
                        class="h-[15px] w-[15px]"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="block truncate text-[13.5px] font-semibold">
                        {{ node.name }}
                    </p>
                    <span class="text-muted-foreground text-xs">
                        {{ definition?.label ?? 'Type inconnu' }} ·
                        {{ category?.label ?? '—' }}
                    </span>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8"
                    aria-label="Fermer l'inspecteur"
                    @click="emit('update:open', false)"
                >
                    <PanelRightClose class="h-4 w-4" />
                </Button>
            </div>

            <Tabs v-model="tab" class="mx-4 mt-3">
                <TabsList class="w-full">
                    <TabsTrigger value="settings">Paramètres</TabsTrigger>
                    <TabsTrigger value="in">Entrées</TabsTrigger>
                    <TabsTrigger value="out">Sorties</TabsTrigger>
                </TabsList>
            </Tabs>

            <!-- Onglet Paramètres : nom + champs du schéma du catalogue -->
            <div v-if="tab === 'settings'" class="flex flex-col gap-4 p-4">
                <div class="grid gap-2">
                    <Label for="node-name">Nom du node</Label>
                    <Input
                        id="node-name"
                        :model-value="node.name"
                        :disabled="!canUpdateWorkflow"
                        data-test="inspector-node-name"
                        @update:model-value="renameNode"
                    />
                </div>

                <div
                    v-for="field in definition?.fields ?? []"
                    :key="field.key"
                    class="grid gap-2"
                >
                    <Label :for="`node-field-${field.key}`">{{
                        field.label
                    }}</Label>

                    <Select
                        v-if="field.type === 'select'"
                        :model-value="String(configOf(field.key))"
                        :disabled="!canUpdateWorkflow"
                        @update:model-value="
                            (value) => setField(field.key, String(value))
                        "
                    >
                        <SelectTrigger
                            :id="`node-field-${field.key}`"
                            class="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in field.options ?? []"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Textarea
                        v-else-if="field.type === 'textarea'"
                        :id="`node-field-${field.key}`"
                        :model-value="String(configOf(field.key))"
                        :placeholder="field.placeholder ?? undefined"
                        :disabled="!canUpdateWorkflow"
                        rows="3"
                        :class="{ 'font-mono text-[13px]': field.mono }"
                        @update:model-value="
                            (value) => setField(field.key, String(value))
                        "
                    />

                    <div
                        v-else-if="field.type === 'range'"
                        class="flex items-center gap-2.5"
                    >
                        <input
                            :id="`node-field-${field.key}`"
                            type="range"
                            class="accent-brand flex-1"
                            :min="field.min ?? 0"
                            :max="field.max ?? 1"
                            :step="field.step ?? 0.1"
                            :value="Number(configOf(field.key))"
                            :disabled="!canUpdateWorkflow"
                            @input="setRangeField(field.key, $event)"
                        />
                        <span class="font-mono text-sm tabular-nums">{{
                            configOf(field.key)
                        }}</span>
                    </div>

                    <Input
                        v-else
                        :id="`node-field-${field.key}`"
                        :model-value="String(configOf(field.key))"
                        :placeholder="field.placeholder ?? undefined"
                        :disabled="!canUpdateWorkflow"
                        :class="{ 'font-mono text-[13px]': field.mono }"
                        @update:model-value="
                            (value) => setField(field.key, String(value))
                        "
                    />
                </div>

                <template v-if="canUpdateWorkflow">
                    <Separator />
                    <Button
                        variant="destructive"
                        size="sm"
                        class="w-full"
                        data-test="inspector-delete-node"
                        @click="emit('removeNode', node.key)"
                    >
                        <Trash2 class="h-3.5 w-3.5" /> Supprimer ce node
                    </Button>
                </template>
            </div>

            <!-- Onglet Entrées : aperçu simulé (le moteur n'expose pas l'input par node) -->
            <div v-else-if="tab === 'in'" class="flex flex-col gap-3 p-4">
                <p class="text-muted-foreground text-xs">
                    Aperçu simulé — exemples de démonstration, sans exécution
                    réelle.
                </p>
                <pre
                    class="bg-muted/60 overflow-x-auto rounded-md border p-3 font-mono text-[11px] leading-relaxed whitespace-pre"
                    >{{ previewJson }}</pre>
                <Alert class="text-[12.5px]">
                    <Braces />
                    <AlertDescription>
                        Référencez ces valeurs avec
                        <code class="font-mono" v-pre>{{ node.variable }}</code>
                        dans les nodes suivants.
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Onglet Sorties : résultat réel du dernier test, sinon aperçu -->
            <div v-else class="flex flex-col gap-3 p-4">
                <template v-if="nodeResult">
                    <p
                        class="text-muted-foreground text-xs"
                        data-test="inspector-real-output"
                    >
                        Résultat du dernier test
                        <template v-if="nodeResult.status !== 'skipped'">
                            — {{ nodeResult.durationMs }} ms
                        </template>
                    </p>
                    <pre
                        v-if="nodeResult.status !== 'skipped'"
                        class="bg-muted/60 overflow-x-auto rounded-md border p-3 font-mono text-[11px] leading-relaxed whitespace-pre"
                        >{{ outputJson }}</pre>
                    <p
                        v-else
                        class="text-muted-foreground text-xs"
                        data-test="inspector-skipped-output"
                    >
                        Node non exécuté pendant le test — branche non prise ou
                        hors parcours.
                    </p>
                    <p
                        v-if="nodeResult.error"
                        class="text-destructive text-xs"
                        data-test="inspector-node-error"
                    >
                        {{ nodeResult.error.message }}
                    </p>
                </template>
                <template v-else>
                    <p class="text-muted-foreground text-xs">
                        Aperçu simulé — exemples de démonstration, sans
                        exécution réelle.
                    </p>
                    <pre
                        class="bg-muted/60 overflow-x-auto rounded-md border p-3 font-mono text-[11px] leading-relaxed whitespace-pre"
                        >{{ previewJson }}</pre>
                </template>
                <Alert class="text-[12.5px]">
                    <Braces />
                    <AlertDescription>
                        Référencez ces valeurs avec
                        <code class="font-mono" v-pre>{{ node.variable }}</code>
                        dans les nodes suivants.
                    </AlertDescription>
                </Alert>
            </div>
        </template>

        <!-- Rien de sélectionné : aide + statut du workflow (A3) -->
        <template v-else>
            <div class="flex items-center gap-2 border-b px-4 py-3.5">
                <b class="text-[13.5px]">Inspecteur</b>
                <Button
                    variant="ghost"
                    size="icon"
                    class="ml-auto h-8 w-8"
                    aria-label="Fermer l'inspecteur"
                    @click="emit('update:open', false)"
                >
                    <PanelRightClose class="h-4 w-4" />
                </Button>
            </div>

            <div
                class="text-muted-foreground flex flex-col items-center gap-2.5 px-5.5 py-10 text-center text-[13px]"
            >
                <Move class="text-input h-6 w-6" />
                <p>
                    Sélectionnez un node pour éditer ses paramètres,<br />
                    ou déposez-en un depuis la palette.<br />
                    Glissez d'une sortie vers une entrée pour relier.
                </p>
            </div>

            <div class="flex flex-col gap-3.5 border-t p-4">
                <div class="grid gap-2">
                    <Label>Statut du workflow</Label>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1"
                            :class="{
                                'bg-foreground text-background hover:bg-foreground border-foreground':
                                    status === 'draft',
                            }"
                            :disabled="!canUpdateWorkflow"
                            data-test="workflow-set-draft"
                            @click="emit('changeStatus', 'draft')"
                        >
                            Brouillon
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1"
                            :class="{
                                'bg-foreground text-background hover:bg-foreground border-foreground':
                                    status === 'active',
                            }"
                            :disabled="!canUpdateWorkflow"
                            data-test="workflow-set-active"
                            @click="emit('changeStatus', 'active')"
                        >
                            Activer
                        </Button>
                    </div>
                </div>
                <p class="text-muted-foreground text-xs">
                    Un workflow actif répond à son déclencheur dès qu'il est
                    enregistré.
                </p>
            </div>
        </template>
    </aside>
</template>

<style scoped lang="scss">
/*
 * Repli de l'inspecteur (maquette : transition margin-right).
 */
.inspector-hidden {
    margin-right: -296px;
}
</style>
