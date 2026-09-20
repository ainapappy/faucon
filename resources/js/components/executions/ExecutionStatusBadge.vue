<script setup lang="ts">
import { Ban, CircleCheck, CircleX, Clock, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { WorkflowExecutionStatus } from '@/types';

/*
 * Badge de statut d'une exécution persistée (phase 7) — miroir du helper
 * `status()` de la maquette executions.html. Règle CVD : la couleur ne porte
 * jamais l'information seule — icône + libellé systématiques.
 */
const props = defineProps<{
    status: WorkflowExecutionStatus;
}>();

const PRESENTATION = {
    completed: {
        label: 'Succès',
        icon: CircleCheck,
        classes: 'border-transparent bg-success-soft text-success',
    },
    failed: {
        label: 'Échec',
        icon: CircleX,
        classes: 'border-transparent bg-danger-soft text-danger',
    },
    running: {
        label: 'En cours',
        icon: LoaderCircle,
        classes: 'border-transparent bg-info-soft text-info',
    },
    pending: {
        label: 'En file',
        icon: Clock,
        classes: 'border-transparent bg-secondary text-secondary-foreground',
    },
    cancelled: {
        label: 'Annulée',
        icon: Ban,
        classes: 'border-current text-muted-foreground',
    },
} as const;

const presentation = computed(() => PRESENTATION[props.status]);
</script>

<template>
    <Badge
        variant="outline"
        class="gap-1 whitespace-nowrap"
        :class="presentation.classes"
    >
        <component
            :is="presentation.icon"
            class="size-3"
            :class="{ 'animate-spin': status === 'running' }"
        />
        {{ presentation.label }}
    </Badge>
</template>
