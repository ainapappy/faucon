<script setup lang="ts">
import { CircleCheck, Clock, LoaderCircle } from '@lucide/vue';
import { useWelcomeDemo } from '@/composables/useWelcomeDemo';
import { landingPct } from '@/lib/landing';
import { scrollReveal } from '@/lib/scrollReveal';

// Directive locale d'apparition au scroll (maquette : v-reveal).
const vReveal = scrollReveal;

const {
    demoNodes,
    demoEdgeKeys,
    demoEdges,
    nodeState,
    edgeState,
    dot,
    runOk,
    runDuration,
    logIndex,
    currentLog,
    logDone,
} = useWelcomeDemo();
</script>

<template>
    <div id="demo" class="demo-card anim-in d-6" v-reveal>
        <div class="demo-head">
            <span class="live-dot" aria-hidden="true"></span>
            <b>Exécution en direct</b>
            <span class="mono-badge">wf_lead_capture</span>
            <span class="spacer"></span>
            <Transition name="fade" mode="out-in">
                <span v-if="!runOk" key="run" class="head-badge is-info">
                    <LoaderCircle :size="12" class="spin" />
                    En cours
                </span>
                <span v-else key="ok" class="head-badge is-success">
                    <CircleCheck :size="12" />
                    Succès · {{ runDuration }}
                </span>
            </Transition>
        </div>

        <div class="wf-scroll">
            <div class="wf-canvas">
                <!-- Arêtes + paquet voyageant -->
                <svg
                    viewBox="0 0 660 280"
                    preserveAspectRatio="xMidYMid meet"
                    aria-hidden="true"
                >
                    <path
                        v-for="key in demoEdgeKeys"
                        :key="key"
                        class="edge"
                        :class="'is-' + edgeState[key]"
                        :style="{ '--edge-color': demoEdges[key].color }"
                        :d="demoEdges[key].d"
                    />
                    <circle
                        v-if="dot.on"
                        class="wf-dot"
                        :cx="dot.x"
                        :cy="dot.y"
                        r="4.5"
                        fill="var(--brand)"
                    />
                </svg>

                <!-- Nodes -->
                <div
                    v-for="node in demoNodes"
                    :key="node.id"
                    class="wf-node"
                    :class="nodeState[node.id]"
                    :style="{
                        left: landingPct(node.x, 660),
                        top: landingPct(node.y, 280),
                        width: landingPct(node.w, 660),
                        height: landingPct(node.h, 280),
                        '--node-color': node.color,
                    }"
                >
                    <span
                        class="n-icon"
                        :style="{
                            background:
                                'color-mix(in srgb, ' +
                                node.color +
                                ' 13%, transparent)',
                            color: node.color,
                        }"
                    >
                        <component :is="node.icon" :size="15" />
                    </span>
                    <span class="n-meta">
                        <b>{{ node.label }}</b>
                        <small>{{ node.sub }}</small>
                    </span>
                    <span class="n-state">
                        <component
                            :is="
                                nodeState[node.id] === 'running'
                                    ? LoaderCircle
                                    : nodeState[node.id] === 'done'
                                      ? CircleCheck
                                      : Clock
                            "
                            :size="nodeState[node.id] === 'pending' ? 13 : 14"
                            :class="{ spin: nodeState[node.id] === 'running' }"
                        />
                    </span>
                </div>

                <!-- Étiquettes de branches -->
                <span
                    class="wf-tag"
                    :class="{ on: edgeState.ce !== 'idle' }"
                    style="left: 57.5%; top: 49.8%; --tag-color: var(--cat-2)"
                >
                    <i style="background: var(--cat-2)"></i>autre
                </span>
                <span
                    class="wf-tag"
                    :class="{ on: edgeState.cd !== 'idle' }"
                    style="left: 78.6%; top: 56.2%; --tag-color: var(--cat-4)"
                >
                    <i style="background: var(--cat-4)"></i>lead
                </span>
            </div>
        </div>

        <div class="wf-foot">
            <Transition name="fade" mode="out-in">
                <span :key="logIndex" class="wf-log">
                    <template v-if="logDone">
                        <span class="ok">✓</span> {{ currentLog }}
                    </template>
                    <template v-else>
                        <span class="pending">→</span> {{ currentLog }}
                    </template>
                </span>
            </Transition>
            <span class="foot-caption">node · durée · sortie</span>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.demo-card {
    max-width: 880px;
    margin: 52px auto 0;
    background: var(--card);
    color: var(--card-foreground);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    text-align: left;
}

.demo-head {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 13px 18px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;

    .spacer {
        margin-left: auto;
    }
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: var(--r-full);
    background: var(--success);
    animation: welcome-pulse-dot 2s ease-out infinite;
}

.head-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 9px;
    border-radius: var(--r-full);
    font-size: 11.5px;
    font-weight: 500;
    line-height: 1.6;
    white-space: nowrap;

    &.is-info {
        background: var(--info-soft);
        color: var(--info);
    }

    &.is-success {
        background: var(--success-soft);
        color: var(--success);
    }
}

.mono-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: var(--r-full);
    border: 1px solid var(--border);
    color: var(--foreground);
    font-family: var(--font-mono);
    font-size: 10.5px;
    white-space: nowrap;
}

.spin {
    animation: welcome-spin 1s linear infinite;
}

// ---------- Canvas ----------
.wf-scroll {
    overflow-x: auto;
    scrollbar-width: thin;
}

.wf-canvas {
    position: relative;
    min-width: 640px;
    aspect-ratio: 660 / 280;

    > svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }
}

.edge {
    fill: none;
    stroke: var(--border);
    stroke-width: 2;
    transition: stroke 0.3s;

    &.is-done {
        stroke: color-mix(
            in srgb,
            var(--edge-color, var(--brand)) 38%,
            var(--border)
        );
    }

    &.is-live {
        stroke: var(--edge-color, var(--brand));
        stroke-dasharray: 7 5;
        animation: welcome-edge-march 0.45s linear infinite;
    }
}

.wf-dot {
    filter: drop-shadow(
        0 0 5px color-mix(in srgb, var(--brand) 60%, transparent)
    );
}

.wf-node {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 13px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow-xs);
    text-align: left;
    transition:
        opacity 0.35s,
        border-color 0.3s,
        box-shadow 0.3s;

    .n-icon {
        flex: none;
        width: 30px;
        height: 30px;
        border-radius: 9px;
        display: grid;
        place-items: center;
    }

    .n-meta {
        min-width: 0;

        b {
            display: block;
            font-size: 13px;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        small {
            display: block;
            font-size: 10.5px;
            color: var(--muted-foreground);
            font-family: var(--font-mono);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    .n-state {
        margin-left: auto;
        color: var(--muted-foreground);
    }

    &.pending {
        opacity: 0.45;
    }

    &.running {
        border-color: var(--node-color);
        box-shadow: 0 0 0 4px
            color-mix(in srgb, var(--node-color) 14%, transparent);

        .n-state {
            color: var(--node-color);
        }
    }

    &.done .n-state {
        color: var(--success);
    }
}

.wf-tag {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 8px;
    border-radius: var(--r-full);
    background: var(--card);
    border: 1px solid var(--border);
    font-size: 10px;
    font-family: var(--font-mono);
    color: var(--muted-foreground);
    box-shadow: var(--shadow-xs);

    i {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    &.on {
        color: var(--foreground);
        border-color: color-mix(in srgb, var(--tag-color) 45%, var(--border));
    }
}

// ---------- Pied : journal ----------
.wf-foot {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 47px;
    padding: 10px 18px;
    border-top: 1px solid var(--border);
    background: color-mix(in srgb, var(--secondary) 45%, transparent);
    font-size: 12.5px;
}

.wf-log {
    font-family: var(--font-mono);
    font-size: 11.5px;
    color: var(--muted-foreground);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;

    .ok {
        color: var(--success);
    }

    .pending {
        color: var(--muted-foreground);
    }
}

.foot-caption {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: var(--r-full);
    background: var(--secondary);
    color: var(--secondary-foreground);
    font-family: var(--font-mono);
    font-size: 10px;
    flex: none;
    white-space: nowrap;
}

// ---------- Transitions & animations ----------
.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.fade-enter-from {
    opacity: 0;
    transform: translateY(4px);
}

.fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@keyframes welcome-edge-march {
    to {
        stroke-dashoffset: -12;
    }
}

@keyframes welcome-spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes welcome-pulse-dot {
    0% {
        box-shadow: 0 0 0 0 color-mix(in srgb, var(--success) 45%, transparent);
    }

    70% {
        box-shadow: 0 0 0 6px transparent;
    }

    100% {
        box-shadow: 0 0 0 0 transparent;
    }
}

@media (prefers-reduced-motion: reduce) {
    .edge.is-live,
    .wf-node.running .n-state .spin,
    .spin,
    .live-dot {
        animation: none;
    }

    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
