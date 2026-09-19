<script setup lang="ts">
import { CircleCheck, Terminal, X } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { SimulationLogEntry, SimulationSummary } from '@/types';

const props = defineProps<{
    open: boolean;
    logs: SimulationLogEntry[];
    running: boolean;
    summary: SimulationSummary | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const logsBox = ref<HTMLElement | null>(null);

// Le journal suit la dernière ligne, comme la maquette.
watch(
    () => props.logs.length,
    async () => {
        await nextTick();
        if (logsBox.value) {
            logsBox.value.scrollTop = logsBox.value.scrollHeight;
        }
    },
);
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
            ref="logsBox"
            class="flex-1 overflow-y-auto px-4 pt-2.5 pb-3.5 font-mono text-[11.5px] leading-[1.75]"
        >
            <p
                v-for="(entry, index) in logs"
                :key="`${entry.time}-${index}`"
                class="flex gap-2.5"
            >
                <time class="text-muted-foreground flex-none">{{
                    entry.time
                }}</time>
                <span class="text-brand-ink min-w-[130px] flex-none">{{
                    entry.source
                }}</span>
                <span
                    class="text-foreground"
                    :class="{
                        'text-success': entry.level === 'ok',
                        'text-destructive': entry.level === 'error',
                        'text-info': entry.level === 'info',
                    }"
                    >{{ entry.message }}</span
                >
            </p>

            <p
                v-if="logs.length === 0"
                class="text-muted-foreground py-1.5 font-sans"
            >
                Lancez <b>Exécuter</b> pour tester le graphe node par node —
                simulation locale, aucune donnée réelle.
            </p>
        </div>

        <div
            v-if="summary && !running"
            class="bg-success-soft flex flex-none items-center gap-2.5 border-t px-4 py-2 text-[12.5px]"
            data-test="exec-summary"
        >
            <CircleCheck class="text-success h-[15px] w-[15px]" />
            <p>
                <b>Succès</b> — {{ summary.nodes }} nodes ·
                {{ (summary.durationMs / 1000).toFixed(1) }} s
            </p>
        </div>
    </div>
</template>

<style scoped lang="scss">
.backdrop-transition {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
