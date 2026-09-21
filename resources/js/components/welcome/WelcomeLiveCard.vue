<script setup lang="ts">
import { Activity } from '@lucide/vue';
import { ref } from 'vue';
import { useWelcomeLive } from '@/composables/useWelcomeLanding';

const liveCard = ref<HTMLElement | null>(null);

const { chartDrawn, chartPaths, feed } = useWelcomeLive(liveCard);
</script>

<template>
    <div ref="liveCard" class="live-card" :class="{ drawn: chartDrawn }">
        <span
            class="b-icon"
            style="
                background: color-mix(in srgb, var(--cat-4) 12%, transparent);
                color: var(--cat-4);
            "
        >
            <Activity :size="18" />
        </span>
        <h3>Observabilité en direct</h3>
        <p>
            Chaque exécution est tracée node par node : durées, entrées /
            sorties, erreurs explicites.
        </p>
        <div class="live-grid">
            <div class="live-chart-wrap">
                <svg viewBox="0 0 260 72" width="100%" aria-hidden="true">
                    <path
                        class="chart-area"
                        :d="chartPaths.area"
                        fill="var(--brand-soft)"
                    ></path>
                    <path
                        class="chart-line"
                        :d="chartPaths.line"
                        fill="none"
                        stroke="var(--brand)"
                        stroke-width="2"
                        stroke-linecap="round"
                    ></path>
                </svg>
                <div class="chart-caption">
                    <span>7 derniers jours</span>
                    <span>+38 % de runs</span>
                </div>
            </div>
            <div class="relative">
                <TransitionGroup name="feed" tag="div" class="feed">
                    <div v-for="item in feed" :key="item.id" class="feed-item">
                        <component
                            :is="item.icon"
                            :size="14"
                            :style="{ color: item.color, flex: 'none' }"
                        />
                        <span class="f-text">{{ item.text }}</span>
                        <span class="f-time">{{ item.time }}</span>
                    </div>
                </TransitionGroup>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.live-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    padding: 22px;
    overflow: hidden;
    background: var(--card);
    color: var(--card-foreground);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    transition:
        transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1),
        box-shadow 0.25s,
        border-color 0.25s;

    &:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-pop);
    }

    h3 {
        font-size: 16px;
        font-weight: 650;
    }

    p {
        font-size: 13.5px;
        line-height: 1.55;
        color: var(--muted-foreground);
    }
}

.b-icon {
    flex: none;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
}

// ---------- Graphe + flux live ----------
.live-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 18px;
    align-items: end;
    margin-top: auto;

    @media (max-width: 960px) {
        grid-template-columns: 1fr;
        align-items: stretch;
    }
}

.live-chart-wrap {
    min-width: 0;

    .chart-caption {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: var(--muted-foreground);
        margin-top: 6px;
        font-family: var(--font-mono);
    }
}

.chart-line {
    stroke-dasharray: 620;
    stroke-dashoffset: 620;
    transition: stroke-dashoffset 1.4s cubic-bezier(0.3, 0.7, 0.3, 1) 0.15s;
}

.drawn .chart-line {
    stroke-dashoffset: 0;
}

.chart-area {
    opacity: 0;
    transition: opacity 0.8s ease 0.9s;
}

.drawn .chart-area {
    opacity: 1;
}

.feed {
    display: flex;
    flex-direction: column;
    gap: 7px;
    position: relative;
}

.feed-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 11px;
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    background: var(--card);
    font-size: 12.5px;

    .f-text {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .f-time {
        font-size: 10.5px;
        color: var(--muted-foreground);
        font-family: var(--font-mono);
        flex: none;
    }
}

.feed-enter-active {
    transition:
        opacity 0.4s ease,
        transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.feed-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.feed-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
    position: absolute;
    width: 100%;
}

.feed-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

.feed-move {
    transition: transform 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@media (prefers-reduced-motion: reduce) {
    .feed-enter-active,
    .feed-leave-active,
    .feed-move {
        transition: none;
    }
}
</style>
