<script setup lang="ts">
import { CircleCheck, CircleX, Terminal, X } from '@lucide/vue';
import { computed } from 'vue';
import ExecDrawerNodeRow from '@/components/builder/ExecDrawerNodeRow.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatRunSeconds } from '@/composables/useWorkflowTestRun';
import type { ExecutionResult, NodeRunStatus, TestRunState } from '@/types';

/*
 * Tiroir de résultats du « Tester » (maquette builder.html, phase 4) :
 * les nodes sont listés dans l'ordre d'exécution de la réponse serveur —
 * statut (ok / error / skipped), durée, sortie réelle repliable, erreur FR.
 * Pendant la relecture animée, les lignes suivent `statuses` (spinner puis
 * statut final) ; le résumé n'apparaît qu'à la fin, comme la maquette.
 */
const props = defineProps<{
    open: boolean;
    state: TestRunState;
    result: ExecutionResult | null;
    statuses: Record<string, NodeRunStatus>;
}>();

const emit = defineEmits<{
    close: [];
}>();

const running = computed(() => props.state === 'running');

const summaryMessage = computed(() => {
    if (!props.result) {
        return '';
    }
    if (props.result.status === 'failed') {
        return props.result.errors[0]?.message ?? 'Le test a échoué.';
    }
    return `${props.result.nodes.length} nodes · ${formatRunSeconds(props.result.durationMs)}`;
});
</script>

<template>
    <div
        class="bg-card backdrop-transition absolute inset-x-0 bottom-0 z-20 flex h-60 flex-col border-t shadow-[0_-8px_24px_-12px_hsl(0_0%_0%/0.18)]"
        :class="{ 'translate-y-full': !open, 'translate-y-0': open }"
        data-test="exec-drawer"
    >
        <div class="flex flex-none items-center gap-2.5 border-b px-4 py-2.5">
            <Terminal class="text-muted-foreground h-3.5 w-3.5" />
            <b class="text-[13px]">Journal d'exécution</b>
            <Badge
                v-if="running"
                class="gap-1.5"
                data-test="exec-drawer-running"
            >
                <Spinner class="h-2.5 w-2.5" /> En cours
            </Badge>
            <Button
                variant="ghost"
                size="icon"
                class="ml-auto h-7 w-7"
                aria-label="Fermer le journal"
                @click="emit('close')"
            >
                <X class="h-3.5 w-3.5" />
            </Button>
        </div>

        <div
            class="flex-1 overflow-y-auto px-4 pt-1.5 pb-3.5 font-mono text-[11.5px] leading-[1.75]"
            data-test="exec-drawer-nodes"
        >
            <ExecDrawerNodeRow
                v-for="nodeRun in result?.nodes ?? []"
                :key="nodeRun.nodeKey"
                :node-run="nodeRun"
                :status="statuses[nodeRun.nodeKey] ?? 'idle'"
            />

            <p v-if="!result" class="text-muted-foreground py-1.5 font-sans">
                Lancez <b>Tester</b> pour exécuter le graphe node par node avec
                un input d'échantillon.
            </p>
        </div>

        <div
            v-if="result && !running"
            class="flex flex-none items-center gap-2.5 border-t px-4 py-2 text-[12.5px]"
            :class="
                result.status === 'completed'
                    ? 'bg-success-soft'
                    : 'bg-danger-soft'
            "
            data-test="exec-summary"
        >
            <CircleCheck
                v-if="result.status === 'completed'"
                class="text-success h-3.75 w-3.75"
            />
            <CircleX v-else class="text-destructive h-3.75 w-3.75" />
            <p>
                <template v-if="result.status === 'completed'">
                    <b>Succès</b> — {{ summaryMessage }}
                </template>
                <template v-else>
                    <b>Échec</b> — {{ summaryMessage }}
                </template>
            </p>
        </div>
    </div>
</template>

<style scoped lang="scss">
.backdrop-transition {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
