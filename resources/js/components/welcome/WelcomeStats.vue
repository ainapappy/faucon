<script setup lang="ts">
import { useWelcomeStats } from '@/composables/useWelcomeLanding';
import { formatLandingNumber } from '@/lib/landing';

const { stats } = useWelcomeStats();
</script>

<template>
    <div class="stats anim-in d-5">
        <div class="stat">
            <b>{{ formatLandingNumber(stats.nodes) }}+</b>
            <span>types de nodes</span>
        </div>
        <div class="stat">
            <b>{{ formatLandingNumber(stats.tpl) }}+</b>
            <span>templates prêts</span>
        </div>
        <div class="stat">
            <b>{{ formatLandingNumber(stats.avg) }} s</b>
            <span>exécution moyenne</span>
        </div>
        <div class="stat">
            <b>{{ formatLandingNumber(stats.logs) }} %</b>
            <span>d'exécutions tracées</span>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    max-width: 880px;
    margin: 52px auto 0;

    .stat {
        padding: 4px 18px;
        text-align: center;

        &:not(:first-child) {
            border-left: 1px solid var(--border);
        }

        b {
            display: block;
            font-size: 30px;
            font-weight: 700;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
        }

        span {
            font-size: 12.5px;
            color: var(--muted-foreground);
        }
    }

    @media (max-width: 760px) {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px 0;

        .stat:nth-child(3) {
            border-left: none;
        }
    }

    @media (max-width: 620px) {
        grid-template-columns: repeat(2, 1fr);

        .stat {
            border-left: none;
        }
    }
}
</style>
