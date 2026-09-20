<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Layers, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as templatesIndex } from '@/routes/templates';
import { store } from '@/routes/workflows';

/*
 * Création de workflow (maquette workflows.html, amendement A1 validé) : un
 * seul chemin d'instanciation des templates — la galerie. Le lien remplace
 * l'ancien select statique ; la création reste un graphe vide, choisir un
 * modèle se fait sur un aperçu visuel dans la galerie.
 */
const page = usePage();

const templatesUrl = computed(
    () =>
        templatesIndex({ current_team: page.props.currentTeam?.slug ?? '' })
            .url,
);

const open = ref(false);
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent data-test="create-workflow-dialog">
            <Form
                :key="formKey"
                v-bind="store.form()"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Nouveau workflow</DialogTitle>
                    <DialogDescription>
                        Partez de zéro ou d'un template — vous pourrez tout
                        éditer ensuite.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="workflow-name"
                        >Nom <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="workflow-name"
                        name="name"
                        placeholder="Ex. Traitement des nouveaux leads"
                        required
                        autofocus
                        data-test="create-workflow-name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="workflow-description">Description</Label>
                    <Textarea
                        id="workflow-description"
                        name="description"
                        rows="3"
                        placeholder="À quoi sert ce workflow ?"
                        data-test="create-workflow-description"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label>Partir d'un template</Label>
                    <Button
                        variant="outline"
                        as-child
                        data-test="create-workflow-templates-link"
                    >
                        <Link
                            :href="templatesUrl"
                            class="justify-start"
                            prefetch
                        >
                            <Layers class="h-4 w-4" />
                            Parcourir les templates
                            <ArrowUpRight class="ml-auto h-3.5 w-3.5" />
                        </Link>
                    </Button>
                    <p class="text-muted-foreground text-xs">
                        Choisissez un modèle dans la galerie — son graphe est
                        dupliqué dans votre équipe, tout reste éditable.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="ghost" type="button">Annuler</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="create-workflow-submit"
                    >
                        <Plus />
                        {{ processing ? 'Création…' : 'Créer le workflow' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
