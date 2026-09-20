<script setup lang="ts">
import {
    Link as LinkIcon,
    Lock,
    Pencil,
    PlugZap,
    Ellipsis,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import {
    integrationMetaLabel,
    integrationTypePresentation,
    testStatus,
    testStatusPresentation,
} from '@/lib/integrationTypes';
import type { IntegrationSummary } from '@/types';

type Props = {
    integration: IntegrationSummary;
    /** Test de connexion en vol pour CETTE carte (spinner + libellé « Test… »). */
    testing?: boolean;
    /** Menu Modifier + bouton Tester (Policy test = integration:update). */
    canUpdate?: boolean;
    /** Item Supprimer du menu (integration:delete). */
    canDelete?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    testing: false,
    canUpdate: false,
    canDelete: false,
});

const emit = defineEmits<{
    edit: [integration: IntegrationSummary];
    remove: [integration: IntegrationSummary];
    test: [integration: IntegrationSummary];
}>();

const presentation = computed(() =>
    integrationTypePresentation(props.integration.type),
);

const metaLabel = computed(() =>
    integrationMetaLabel(props.integration.meta, props.integration.type),
);

const status = computed(() =>
    testStatusPresentation(testStatus(props.integration.lastTestSucceeded)),
);

const usedByLabel = computed(() => {
    const count = props.integration.usedByWorkflows;

    return `Utilisé par ${count} workflow${count > 1 ? 's' : ''}`;
});
</script>

<template>
    <div
        data-test="integration-card"
        class="bg-card hover:border-ring/40 flex flex-col gap-3 rounded-lg border p-4 transition-colors"
    >
        <div class="flex items-center gap-2.5">
            <span
                :class="[
                    'flex size-8.5 flex-none items-center justify-center rounded-md text-sm font-bold',
                    presentation.avatarClass,
                ]"
                aria-hidden="true"
            >
                {{ presentation.letter }}
            </span>

            <div class="min-w-0 flex-1">
                <span class="block truncate text-[13.5px] font-semibold">
                    {{ integration.name }}
                </span>
                <span class="text-muted-foreground text-xs">
                    {{ presentation.label }}
                </span>
            </div>

            <span
                data-test="integration-test-status"
                :class="[
                    'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                    status.badgeClass,
                ]"
            >
                <span
                    v-if="status.showDot"
                    class="size-1.5 rounded-full bg-current"
                    aria-hidden="true"
                />
                {{ status.label }}
            </span>
        </div>

        <!-- Ligne méta non secrète : jamais de credential ici, le backend ne les renvoie pas. -->
        <div
            class="bg-muted/60 flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 font-mono text-[11.5px]"
        >
            <LinkIcon class="text-muted-foreground size-3.25 flex-none" />
            <span class="flex-1 truncate">
                {{ metaLabel }}
            </span>
            <Badge variant="secondary" class="ml-auto flex-none gap-1">
                <Lock class="size-2.75" />
                Secret masqué
            </Badge>
        </div>

        <div class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground text-xs">
                {{ usedByLabel }}
            </span>

            <div
                v-if="canUpdate || canDelete"
                class="flex items-center gap-1.5"
            >
                <Button
                    v-if="canUpdate"
                    variant="outline"
                    size="sm"
                    :disabled="testing"
                    data-test="integration-test-button"
                    @click="emit('test', integration)"
                >
                    <Spinner v-if="testing" class="size-3" />
                    <PlugZap v-else class="size-3.25" />
                    {{ testing ? 'Test…' : 'Tester' }}
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Options de l’intégration"
                            data-test="integration-options-button"
                        >
                            <Ellipsis class="size-3.5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-40">
                        <DropdownMenuItem
                            v-if="canUpdate"
                            data-test="integration-edit-item"
                            @click="emit('edit', integration)"
                        >
                            <Pencil class="size-3.5" />
                            Modifier
                        </DropdownMenuItem>
                        <template v-if="canUpdate && canDelete">
                            <DropdownMenuSeparator />
                        </template>
                        <DropdownMenuItem
                            v-if="canDelete"
                            variant="destructive"
                            data-test="integration-delete-item"
                            @click="emit('remove', integration)"
                        >
                            <Trash2 class="size-3.5" />
                            Supprimer
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </div>
</template>
