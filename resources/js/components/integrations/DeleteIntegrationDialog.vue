<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/integrations';
import type { IntegrationSummary } from '@/types';

type Props = {
    integration: IntegrationSummary | null;
    teamSlug: string;
    open: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);

/*
 * Avertissement d'usage (U5) : la suppression est autorisée même utilisée —
 * les nodes référençant échoueront explicitement à l'exécution.
 */
const usageWarning = computed(() => {
    const count = props.integration?.usedByWorkflows ?? 0;

    if (count === 0) {
        return null;
    }

    return `${count} workflow${count > 1 ? 's' : ''} référence${count > 1 ? 'nt' : ''} cette intégration — les nodes concernés échoueront après suppression.`;
});

function close(): void {
    if (processing.value) {
        return;
    }

    emit('update:open', false);
}

function destroyIntegration(): void {
    if (!props.integration) {
        return;
    }

    processing.value = true;

    /*
     * Visite DELETE : le contrôleur renvoie une redirection vers l'index avec
     * le flash toast (« Integration deleted. ») — la liste se recharge seule.
     */
    router.visit(
        destroy({
            current_team: props.teamSlug,
            integration: props.integration.id,
        }).url,
        {
            method: 'delete',
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                emit('update:open', false);
            },
        },
    );
}
</script>

<template>
    <Dialog :open="props.open" @update:open="close">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    Supprimer « {{ props.integration?.name }} »
                </DialogTitle>
                <DialogDescription>
                    Les credentials seront définitivement effacés. Cette action
                    est irréversible.
                </DialogDescription>
            </DialogHeader>

            <Alert
                v-if="usageWarning"
                class="border-warning/25 bg-warning-soft"
                data-test="integration-delete-warning"
            >
                <TriangleAlert class="text-warning size-4" />
                <AlertDescription>{{ usageWarning }}</AlertDescription>
            </Alert>

            <DialogFooter class="gap-2">
                <Button
                    variant="ghost"
                    :disabled="processing"
                    data-test="integration-delete-cancel"
                    @click="close"
                >
                    Annuler
                </Button>
                <Button
                    variant="destructive"
                    :disabled="processing"
                    data-test="integration-delete-confirm"
                    @click="destroyIntegration"
                >
                    <Spinner v-if="processing" class="size-4" />
                    <Trash2 v-else class="size-4" />
                    Supprimer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
