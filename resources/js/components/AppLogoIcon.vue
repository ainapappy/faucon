<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

/*
 * Logo Faucon (miroir de .knowledge/design/img/logo.svg) : aile en
 * gradient ambre #f59e0b → #ea580c, trait blanc, œil sombre. Le rendu
 * est en couleurs de marque — il ne dépend plus de currentColor.
 * L'id du gradient est unique par instance (useId) pour éviter les
 * collisions de <defs> quand plusieurs logos coexistent sur une page.
 */
const gradientId = `faucon-logo-fw-${useId()}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <defs>
            <linearGradient
                :id="gradientId"
                x1="6"
                y1="40"
                x2="44"
                y2="8"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#f59e0b" />
                <stop offset="1" stop-color="#ea580c" />
            </linearGradient>
        </defs>
        <path
            :fill="`url(#${gradientId})`"
            d="M5 40 C5 22 16 8 42 8 L38 13 L44 14.5 L35.5 19 C31 30 20 38 5 40 Z"
        />
        <path
            fill="none"
            stroke="#ffffff"
            stroke-opacity="0.35"
            stroke-width="2"
            stroke-linecap="round"
            d="M13 32.5 C19 27.5 24 23.5 30 20.5"
        />
        <circle cx="34.2" cy="13.6" r="2" fill="#1c1917" />
    </svg>
</template>
