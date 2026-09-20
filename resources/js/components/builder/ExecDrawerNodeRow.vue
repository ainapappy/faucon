<script setup lang="ts">
import {
    ChevronDown,
    CircleCheck,
    CircleDashed,
    CircleX,
    Minus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AiUsageBadge from '@/components/builder/AiUsageBadge.vue';
import { Spinner } from '@/components/ui/spinner';
import { formatNodeOutput } from '@/composables/useWorkflowTestRun';
import type { NodeRunResult, NodeRunStatus } from '@/types';

/*
 * Ligne de résultat d'un node du tiroir « Tester » (maquette builder.html) :
 * statut (icône + libellé — jamais la couleur seule), durée, sortie réelle
 * repliable rendue en TEXTE et erreur FR compréhensible. Pendant la
 * relecture animée, la ligne suit le statut du composable (`running` →
 * spinner, `idle` → en attente) avant d'appliquer le statut final.
 */
const props = defineProps<{
    nodeRun: NodeRunResult;
    status: NodeRunStatus;
}>();

const outputOpen = ref(false);

const presentation = computed(() => {
    switch (props.status) {
        case 'running':
            return {
                label: 'En cours',
                icon: null,
                class: 'text-info bg-info-soft',
                busy: true,
            };
        case 'ok':
            return {
                label: 'Réussi',
                icon: CircleCheck,
                class: 'text-success bg-success-soft',
                busy: false,
            };
        case 'error':
            return {
                label: 'Erreur',
                icon: CircleX,
                class: 'text-destructive bg-danger-soft',
                busy: false,
            };
        case 'skipped':
            return {
                label: 'Non exécuté',
                icon: Minus,
                class: 'text-muted-foreground bg-muted',
                busy: false,
            };
        default:
            return {
                label: 'En attente',
                icon: CircleDashed,
                class: 'text-muted-foreground bg-muted',
                busy: false,
            };
    }
});

const outputJson = computed(() => formatNodeOutput(props.nodeRun.output));
const hasOutput = computed(() => Object.keys(props.nodeRun.output).length > 0);
const showDuration = computed(
    () => props.status === 'ok' || props.status === 'error',
);
</script>

<template>
    <div
        class="flex flex-col gap-1.5 py-1.5"
        :data-test="`exec-node-${nodeRun.nodeKey}`"
    >
        <div class="flex items-center gap-2.5">
            <span
                class="inline-flex w-[86px] flex-none items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold"
                :class="presentation.class"
                :data-test="`exec-node-status-${nodeRun.nodeKey}`"
            >
                <Spinner v-if="presentation.busy" class="h-2.5 w-2.5" />
                <component
                    :is="presentation.icon"
                    v-else-if="presentation.icon"
                    class="h-3 w-3"
                />
                {{ presentation.label }}
            </span>

            <span class="min-w-0 flex-1 truncate">
                <b class="text-[12px] font-semibold">{{ nodeRun.name }}</b>
                <small
                    class="text-muted-foreground ml-1.5 font-mono text-[10.5px]"
                >
                    {{ nodeRun.type }}
                </small>
            </span>

            <span
                v-if="showDuration"
                class="text-muted-foreground flex-none font-mono text-[10.5px] tabular-nums"
            >
                {{ nodeRun.durationMs }} ms
            </span>
        </div>

        <div
            v-if="nodeRun.error"
            class="text-destructive flex flex-col gap-0.5 border-l-2 py-0.5 pl-2.5 text-[11.5px]"
            :style="{ borderColor: 'var(--danger)' }"
            data-test="exec-node-error"
        >
            <span class="font-mono text-[10px] font-semibold uppercase">
                {{ nodeRun.error.reason }}
            </span>
            <span>{{ nodeRun.error.message }}</span>
        </div>

        <div v-if="status === 'ok' && hasOutput">
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded text-[10.5px] font-semibold"
                :aria-expanded="outputOpen"
                :data-test="`exec-node-output-toggle-${nodeRun.nodeKey}`"
                @click="outputOpen = !outputOpen"
            >
                <ChevronDown
                    class="h-3 w-3 transition-transform"
                    :class="{ '-rotate-90': !outputOpen }"
                />
                Sortie
            </button>
            <!-- Usage tokens des nodes IA (clé usage de la sortie, V10) -->
            <AiUsageBadge :output="nodeRun.output" class="mt-1" />
            <pre
                v-if="outputOpen"
                class="bg-muted/60 mt-1 overflow-x-auto rounded-md border p-2 font-mono text-[10.5px] leading-relaxed whitespace-pre"
                :data-test="`exec-node-output-${nodeRun.nodeKey}`"
                >{{ outputJson }}</pre>
        </div>
    </div>
</template>
