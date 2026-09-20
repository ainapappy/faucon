<script setup lang="ts">
import {
    ArrowLeft,
    Blocks,
    CircleCheck,
    CircleX,
    Maximize2,
    Minus,
    Play,
    Plus,
    Terminal,
    Workflow,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { SaveState, WorkflowStatus } from '@/types';

const name = defineModel<string>('name', { required: true });

const props = defineProps<{
    status: WorkflowStatus;
    saveState: SaveState;
    savedAt: string | null;
    zoomPercent: number;
    running: boolean;
    paletteOpen: boolean;
    journalOpen: boolean;
    canUpdateWorkflow?: boolean;
}>();

const emit = defineEmits<{
    back: [];
    run: [];
    zoomIn: [];
    zoomOut: [];
    fitView: [];
    togglePalette: [];
    toggleJournal: [];
}>();

const statusLabel = computed(() =>
    props.status === 'active' ? 'Actif' : 'En pause',
);

const saveLabel = computed(() => {
    if (props.saveState === 'saved' && props.savedAt) {
        return `Enregistré · ${props.savedAt}`;
    }
    if (props.saveState === 'error') {
        return "Échec de l'enregistrement";
    }
    return 'Enregistrement…';
});
</script>

<template>
    <header
        class="bg-background flex h-13 flex-none items-center gap-2.5 border-b px-3.5"
    >
        <Button
            variant="ghost"
            size="icon"
            class="text-muted-foreground h-8.5 w-8.5"
            aria-label="Retour aux workflows"
            data-test="builder-back"
            @click="emit('back')"
        >
            <ArrowLeft class="h-4.25 w-4.25" />
        </Button>

        <div class="flex min-w-0 items-center gap-2">
            <Workflow class="text-muted-foreground h-3.75 w-3.75 flex-none" />
            <Input
                v-model="name"
                class="hover:bg-muted focus-visible:bg-background h-auto w-57.5 border-transparent px-2 py-1 text-[14.5px] font-semibold shadow-none hover:shadow-none focus-visible:shadow-none"
                :disabled="!canUpdateWorkflow"
                aria-label="Nom du workflow"
                data-test="builder-name"
            />
            <Badge variant="outline" data-test="builder-status">{{
                statusLabel
            }}</Badge>
        </div>

        <span
            v-if="saveState !== 'idle'"
            class="text-muted-foreground inline-flex items-center gap-1.5 text-xs whitespace-nowrap"
            :class="{ 'text-destructive': saveState === 'error' }"
            data-test="builder-save-state"
        >
            <template v-if="saveState === 'saved'">
                <CircleCheck class="text-success h-3.5 w-3.5" />
            </template>
            <template v-else-if="saveState === 'error'">
                <CircleX class="h-3.5 w-3.5" />
            </template>
            <template v-else>
                <Spinner class="text-muted-foreground h-3 w-3" />
            </template>
            {{ saveLabel }}
        </span>

        <div class="ml-auto flex items-center gap-2">
            <div
                class="bg-background ring-border flex items-center gap-0.5 rounded-md p-0.5 shadow-xs ring-1"
            >
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    aria-label="Zoom arrière"
                    @click="emit('zoomOut')"
                >
                    <Minus class="h-3.5 w-3.5" />
                </Button>
                <span
                    class="text-muted-foreground w-11 text-center text-xs tabular-nums"
                    data-test="builder-zoom"
                >
                    {{ zoomPercent }} %
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    aria-label="Zoom avant"
                    @click="emit('zoomIn')"
                >
                    <Plus class="h-3.5 w-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    aria-label="Ajuster à l'écran"
                    @click="emit('fitView')"
                >
                    <Maximize2 class="h-3 w-3" />
                </Button>
            </div>

            <Button
                variant="ghost"
                size="icon"
                class="h-8.5 w-8.5"
                :class="{ 'text-foreground': paletteOpen }"
                aria-label="Palette de nodes"
                @click="emit('togglePalette')"
            >
                <Blocks class="h-4 w-4" />
            </Button>

            <Button
                variant="ghost"
                size="icon"
                class="h-8.5 w-8.5"
                :class="{ 'text-foreground': journalOpen }"
                aria-label="Journal d'exécution"
                @click="emit('toggleJournal')"
            >
                <Terminal class="h-4 w-4" />
            </Button>

            <Button
                :disabled="running"
                data-test="builder-run"
                @click="emit('run')"
            >
                <Spinner v-if="running" class="h-3.5 w-3.5" />
                <Play v-else class="h-3.5 w-3.5" />
                {{ running ? 'En cours…' : 'Tester' }}
            </Button>
        </div>
    </header>
</template>
