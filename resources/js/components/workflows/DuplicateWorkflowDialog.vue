<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Copy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { duplicate } from '@/routes/workflows';
import type { WorkflowListItem } from '@/types';

/*
 * Confirmation de duplication (phase 9, A3 — pattern RemoveMemberModal) :
 * la duplication est additive et réversible, la modal annonce simplement la
 * copie « Nom (copie) » avant la visite POST. Le flash toast de succès reste
 * servi par le contrôleur, comportement externe inchangé.
 */
const props = defineProps<{
    workflow: WorkflowListItem | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();

const processing = ref(false);

const teamName = computed(() => page.props.currentTeam?.name ?? 'votre équipe');

function duplicateWorkflow(): void {
    if (!props.workflow) {
        return;
    }

    router.visit(
        duplicate({
            current_team: page.props.currentTeam?.slug ?? '',
            workflow: props.workflow.id,
        }),
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => emit('update:open', false),
        },
    );
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent data-test="duplicate-workflow-dialog">
            <DialogHeader>
                <DialogTitle>Dupliquer ce workflow ?</DialogTitle>
                <DialogDescription>
                    Une copie nommée
                    <strong>« {{ props.workflow?.name }} (copie) »</strong>
                    sera créée dans {{ teamName }} — l'original reste intact, la
                    copie démarre en pause.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Annuler</Button>
                </DialogClose>

                <Button
                    :disabled="processing"
                    data-test="duplicate-workflow-confirm"
                    @click="duplicateWorkflow"
                >
                    <Copy class="h-4 w-4" />
                    {{ processing ? 'Duplication…' : 'Dupliquer' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
