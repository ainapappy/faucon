<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/workflows';

/*
 * Templates statiques (amendement A2) : présents pour rester fidèles à la
 * maquette mais sans influence sur la création — le graphe naît toujours
 * vide ; les templates réels arrivent en phase 9.
 */
const templates = [
    { value: 'blank', label: 'Griffe vide (déclencheur manuel)' },
    { value: 'support-ia', label: 'Support client IA' },
    { value: 'veille', label: 'Veille de marché' },
    { value: 'onboarding-lead', label: 'Onboarding lead' },
];

const open = ref(false);
const formKey = ref(0);
const template = ref(templates[0]?.value ?? 'blank');

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
                    <Label for="workflow-template">Partir d'un template</Label>
                    <Select v-model="template">
                        <SelectTrigger
                            id="workflow-template"
                            data-test="create-workflow-template"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="item in templates"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-muted-foreground text-xs">
                        Les templates arrivent bientôt — la création reste un
                        graphe vide.
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
