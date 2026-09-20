<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as templatesIndex } from '@/routes/templates';
import { publish } from '@/routes/workflows';
import type { WorkflowDetail, WorkflowListItem } from '@/types';

/*
 * Publication d'un workflow en template d'équipe (phase 9, D11) : nom et
 * description préremplis du workflow, catégorie libre avec suggestions
 * (prop `templateCategories` de la liste — absente dans l'éditeur, qui
 * n'en propose pas). Le backend répond `back()` sans flash : le toast de
 * succès est affiché ici, avec l'action « Voir la galerie ». Un graphe non
 * exécutable revient en `errors.graph` — alerte destructive dans la modal.
 */
const props = withDefaults(
    defineProps<{
        /** Workflow à publier ; sert aussi de source du préremplissage. */
        workflow: WorkflowDetail | WorkflowListItem | null;
        open: boolean;
        /** Catégories des templates visibles — suggestions du champ libre. */
        categories?: string[];
    }>(),
    { categories: () => [] },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({
    name: '',
    description: '',
    category: '',
});

/*
 * `graph` est une clé d'erreur hors forme du formulaire (refus de
 * publication, D5) — décast contrôlé et local, miroir d'IntegrationFormDialog.
 */
const graphError = computed(
    () => (form.errors as Record<string, string | undefined>).graph,
);

/* Préremplissage à chaque ouverture (pattern CreateWorkflowDialog / formKey). */
watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.name = props.workflow?.name ?? '';
        form.description = props.workflow?.description ?? '';
        form.category = '';
        form.clearErrors();
    },
);

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    if (!props.workflow) {
        return;
    }

    form.post(
        publish({
            current_team: teamSlug.value,
            workflow: props.workflow.id,
        }).url,
        {
            onSuccess: () => {
                toast.success('Workflow publié comme template', {
                    description: `« ${form.name} » est disponible dans la galerie, section Mon équipe.`,
                    action: {
                        label: 'Voir la galerie',
                        onClick: () =>
                            router.visit(
                                templatesIndex({
                                    current_team: teamSlug.value,
                                }).url,
                            ),
                    },
                });
                close();
            },
        },
    );
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[85vh] overflow-y-auto sm:max-w-lg"
            data-test="publish-template-dialog"
        >
            <DialogHeader>
                <DialogTitle>Publier comme template</DialogTitle>
                <DialogDescription>
                    Le graphe validé de
                    <strong>« {{ props.workflow?.name }} »</strong>
                    sera partagé avec votre équipe — instanciable depuis la
                    galerie.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <AlertError
                    v-if="graphError"
                    title="Publication impossible"
                    :errors="[graphError]"
                    data-test="publish-template-graph-error"
                />

                <div class="grid gap-2">
                    <Label for="publish-template-name">
                        Nom <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="publish-template-name"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="off"
                        data-test="publish-template-name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="publish-template-description">
                        Description
                    </Label>
                    <Textarea
                        id="publish-template-description"
                        v-model="form.description"
                        rows="3"
                        placeholder="À quoi sert ce template ?"
                        data-test="publish-template-description"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="publish-template-category">
                        Catégorie <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="publish-template-category"
                        v-model="form.category"
                        required
                        autocomplete="off"
                        list="publish-template-categories"
                        placeholder="Ex. Support"
                        data-test="publish-template-category"
                    />
                    <datalist
                        v-if="props.categories.length > 0"
                        id="publish-template-categories"
                    >
                        <option
                            v-for="category in props.categories"
                            :key="category"
                            :value="category"
                        />
                    </datalist>
                    <p class="text-muted-foreground text-xs">
                        Catégorie libre — elle structure les filtres de la
                        galerie.
                    </p>
                    <InputError :message="form.errors.category" />
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="ghost" @click="close">
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="publish-template-submit"
                    >
                        <Upload class="h-4 w-4" />
                        {{
                            form.processing
                                ? 'Publication…'
                                : 'Publier le template'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
