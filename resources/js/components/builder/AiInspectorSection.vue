<script setup lang="ts">
import { ChevronDown, Info } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { aiModeHint } from '@/lib/aiVariables';
import type { NodeTypeDefinition } from '@/types';

/*
 * Section IA de l'inspecteur (phase 6, lot G) — miroir de WebhookInspectorSection :
 * aide-mémoire repliable des variables interpolables (chemins RÉELS calculés
 * par la page depuis les ancêtres du node + contexte trigger), hint du mode
 * (classification : virgules, extraction : format « clé: type ») et rappel de
 * la clé usage exposée par chaque node IA. Entièrement statique — aucun
 * endpoint, aucune donnée externe.
 */
const props = defineProps<{
    /** Définition catalogue du node sélectionné (type `ai.*`). */
    definition: NodeTypeDefinition;
    /** Chemins interpolables calculés par la page (lib/aiVariables : trigger + ancêtres). */
    upstreamVariables: string[];
}>();

/* Aide-mémoire replié par défaut : l'inspecteur reste compact à l'ouverture. */
const cheatSheetOpen = ref(false);

const modeHint = computed(() => aiModeHint(props.definition.type));
</script>

<template>
    <div class="flex flex-col gap-3" data-test="ai-inspector-section">
        <Separator />

        <!-- Aide-mémoire des variables : chemins réels, accolades affichées telles quelles -->
        <Collapsible v-model:open="cheatSheetOpen">
            <CollapsibleTrigger as-child>
                <Button
                    variant="ghost"
                    size="sm"
                    class="-ml-2 w-fit"
                    data-test="ai-variables-toggle"
                >
                    <ChevronDown
                        class="size-3.5 transition-transform"
                        :class="{ '-rotate-90': !cheatSheetOpen }"
                    />
                    Variables disponibles
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div
                    class="bg-muted/40 flex flex-col items-start gap-1.5 rounded-md border p-2.5"
                    data-test="ai-variables-list"
                >
                    <code
                        v-for="(path, index) in upstreamVariables"
                        :key="`${index}-${path}`"
                        class="text-[11px] leading-relaxed break-all"
                        :data-test="`ai-variable-path-${index}`"
                        >{{ path }}</code
                    >
                </div>
            </CollapsibleContent>
        </Collapsible>

        <!-- Hint du mode (classification / extraction) -->
        <p
            v-if="modeHint"
            class="text-muted-foreground text-[12.5px]"
            data-test="ai-mode-hint"
        >
            {{ modeHint }}
        </p>

        <!-- Clé usage : commune aux cinq modes IA (V10) -->
        <Alert class="border-info/30 bg-info-soft text-[12.5px]">
            <Info class="text-info size-4" />
            <AlertDescription data-test="ai-usage-note">
                Chaque node IA expose aussi
                <span class="font-mono">usage.prompt_tokens</span> et
                <span class="font-mono">usage.completion_tokens</span>.
            </AlertDescription>
        </Alert>
    </div>
</template>
