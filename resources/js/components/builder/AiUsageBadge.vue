<script setup lang="ts">
import { Coins } from '@lucide/vue';
import { computed } from 'vue';
import { formatAiUsage } from '@/lib/aiUsage';

/*
 * Badge discret « Tokens : N prompt · N réponse » (phase 6, V10) : rendu
 * seulement quand la sortie du node porte une clé usage de la forme attendue
 * (nodes IA) — invisible pour tout autre node ou toute forme inattendue.
 * La couleur ne porte jamais l'information seule : icône + libellé chiffré.
 */
const props = defineProps<{
    /** Sortie réelle du node (`NodeRunResult.output`). */
    output: Record<string, unknown>;
}>();

const usage = computed(() => formatAiUsage(props.output));
</script>

<template>
    <span
        v-if="usage"
        class="text-muted-foreground bg-muted/70 inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 font-mono text-[10.5px] tabular-nums"
        data-test="ai-usage-badge"
    >
        <Coins class="size-3 flex-none" />
        {{ usage }}
    </span>
</template>
