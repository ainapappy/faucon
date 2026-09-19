<script setup lang="ts">
import { CircleCheck, CircleX } from '@lucide/vue';
import { Handle, Position, type NodeProps } from '@vue-flow/core';
import { computed } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import type { BuilderFlowNode } from '@/composables/useWorkflowBuilder';
import { categoryPresentation } from '@/lib/nodeCategories';
import { nodeIcon } from '@/lib/nodeIcons';

/**
 * Node custom du builder (slot `#node-workflow` de `<VueFlow>`) — 190 × 78 px
 * comme la maquette, styles 100 % tokens via `--node-color` (couleur de
 * CATÉGORIE, jamais la couleur seule : icône + libellé partout).
 */
const props = defineProps<NodeProps<BuilderFlowNode['data']>>();

const definition = computed(() => props.data.definition);

const colorToken = computed(() =>
    definition.value
        ? categoryPresentation(definition.value.category).colorToken
        : 'var(--muted-foreground)',
);

const typeIcon = computed(() => nodeIcon(definition.value?.icon));

const category = computed(() =>
    definition.value ? categoryPresentation(definition.value.category) : null,
);

const status = computed(() => props.data.status ?? 'idle');
</script>

<template>
    <div
        class="group builder-node"
        :class="{
            'builder-node-selected': selected,
            'builder-node-running': status === 'running',
            'builder-node-ok': status === 'ok',
            'builder-node-error': status === 'error',
            // Node non parcouru (branche non prise, isolé…) : estompé, pas d'icône d'état.
            'builder-node-skipped': status === 'skipped',
            'builder-node-dragging': dragging,
        }"
        :style="{ '--node-color': colorToken }"
        :data-test="`builder-node-${id}`"
    >
        <div class="flex items-center gap-2 px-3 pt-2.5 pb-2">
            <span class="builder-node-icon">
                <component :is="typeIcon" class="h-[15px] w-[15px]" />
            </span>

            <span class="min-w-0 flex-1">
                <b
                    class="block truncate text-[12.5px] leading-tight font-semibold"
                    >{{ data.node.name }}</b
                >
                <small class="text-muted-foreground text-[10.5px]">
                    {{ definition?.label ?? 'Type inconnu' }}
                </small>
            </span>

            <span
                class="flex h-[18px] w-[18px] flex-none items-center justify-center"
            >
                <Spinner
                    v-if="status === 'running'"
                    class="text-info h-3 w-3"
                />
                <CircleCheck
                    v-else-if="status === 'ok'"
                    class="builder-node-check text-success h-[15px] w-[15px]"
                />
                <CircleX
                    v-else-if="status === 'error'"
                    class="text-destructive h-[15px] w-[15px]"
                />
            </span>
        </div>

        <div
            class="text-muted-foreground flex items-center gap-1.5 border-t border-dashed px-3 pt-1 pb-2 text-[10.5px]"
            :style="{
                borderColor:
                    'color-mix(in srgb, var(--border) 70%, transparent)',
            }"
        >
            <component
                v-if="category"
                :is="category.icon"
                class="h-[11px] w-[11px]"
            />
            {{ category?.label ?? '—' }}
        </div>

        <Handle
            v-if="definition?.input"
            type="target"
            :position="Position.Left"
            class="builder-port builder-port-in"
            :title="`Entrée — ${data.node.name}`"
        />

        <Handle
            v-for="port in definition?.outputs ?? []"
            :key="port.id"
            :id="port.id"
            type="source"
            :position="Position.Right"
            :style="{ top: `${port.position * 100}%` }"
            class="builder-port builder-port-out"
            :title="`Sortie ${port.label ?? ''} — ${data.node.name}`"
        />
    </div>
</template>

<style scoped lang="scss">
.builder-node {
    width: 190px;
    height: 78px;
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xs);
    cursor: grab;
    transition:
        box-shadow 0.15s ease,
        border-color 0.15s ease,
        transform 0.12s ease;
    user-select: none;

    &:active {
        cursor: grabbing;
    }

    &:hover {
        box-shadow: var(--shadow-md);
        border-color: color-mix(
            in srgb,
            var(--node-color, var(--foreground)) 45%,
            var(--border)
        );
    }
}

.builder-node-selected {
    border-color: var(--brand);
    box-shadow:
        0 0 0 3px var(--brand-soft),
        var(--shadow-md);
}

.builder-node-running {
    border-color: var(--info);
    box-shadow:
        0 0 0 3px var(--info-soft),
        var(--shadow-md);
    animation: builder-node-run 1.1s ease-in-out infinite;
}

.builder-node-ok {
    border-color: color-mix(in srgb, var(--success) 55%, var(--border));
}

.builder-node-error {
    border-color: var(--danger);
    box-shadow:
        0 0 0 3px var(--danger-soft),
        var(--shadow-md);
}

/* Node non parcouru (branche non prise, isolé, aval d'une sortie) : estompé. */
.builder-node-skipped {
    opacity: 0.55;
}

.builder-node-dragging {
    transform: scale(1.03);
    box-shadow: var(--shadow-lg);
    cursor: grabbing;
}

.builder-node-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: calc(var(--radius-md) - 2px);
    background: color-mix(in srgb, var(--node-color) 13%, transparent);
    color: var(--node-color);
    flex: none;
}

.builder-node-check {
    animation: builder-node-pop 0.25s ease;
}

/* ---- Ports (ronds de connexion) ---- */
.builder-port {
    width: 13px;
    height: 13px;
    border-radius: 9999px;
    background: var(--background);
    border: 2px solid var(--node-color, var(--muted-foreground));
    transition:
        transform 0.12s ease,
        box-shadow 0.12s ease;
    z-index: 3;

    &:hover {
        transform: translateY(-50%) scale(1.45);
        box-shadow: 0 0 0 4px
            color-mix(in srgb, var(--node-color) 22%, transparent);
    }
}

.builder-port-in {
    left: -7.5px;
}

.builder-port-out {
    right: -7.5px;
}

@keyframes builder-node-run {
    50% {
        box-shadow:
            0 0 0 6px color-mix(in srgb, var(--info) 12%, transparent),
            var(--shadow-md);
    }
}

@keyframes builder-node-pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
}
</style>
