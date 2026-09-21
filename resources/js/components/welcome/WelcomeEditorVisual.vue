<script setup lang="ts">
/*
 * Mini-graphe SVG décoratif de la carte « Éditeur visuel » (maquette
 * welcome.html) : nodes flottants par catégorie + chips de légende
 * (icône + libellé, jamais la couleur seule).
 */
</script>

<template>
    <div>
        <svg class="deco-svg" viewBox="0 0 320 92" aria-hidden="true">
            <path
                d="M64 24 H110"
                stroke="var(--border)"
                stroke-width="2"
                fill="none"
            ></path>
            <path
                d="M162 24 H208"
                stroke="var(--border)"
                stroke-width="2"
                fill="none"
                stroke-dasharray="5 4"
            ></path>
            <path
                d="M136 42 V64 H240"
                stroke="var(--border)"
                stroke-width="2"
                fill="none"
            ></path>
            <g class="deco-node">
                <rect
                    x="14"
                    y="8"
                    width="50"
                    height="30"
                    rx="9"
                    fill="color-mix(in srgb, var(--cat-1) 13%, var(--card))"
                    stroke="var(--cat-1)"
                    stroke-width="1.5"
                ></rect>
                <circle cx="29" cy="23" r="4" fill="var(--cat-1)"></circle>
                <rect
                    x="38"
                    y="20"
                    width="20"
                    height="5"
                    rx="2.5"
                    fill="var(--foreground)"
                    opacity=".35"
                ></rect>
            </g>
            <g class="deco-node">
                <rect
                    x="110"
                    y="8"
                    width="52"
                    height="30"
                    rx="9"
                    fill="color-mix(in srgb, var(--cat-3) 13%, var(--card))"
                    stroke="var(--cat-3)"
                    stroke-width="1.5"
                ></rect>
                <circle cx="125" cy="23" r="4" fill="var(--cat-3)"></circle>
                <rect
                    x="134"
                    y="20"
                    width="22"
                    height="5"
                    rx="2.5"
                    fill="var(--foreground)"
                    opacity=".35"
                ></rect>
            </g>
            <g class="deco-node">
                <rect
                    x="208"
                    y="8"
                    width="52"
                    height="30"
                    rx="9"
                    fill="color-mix(in srgb, var(--cat-5) 13%, var(--card))"
                    stroke="var(--cat-5)"
                    stroke-width="1.5"
                ></rect>
                <circle cx="223" cy="23" r="4" fill="var(--cat-5)"></circle>
                <rect
                    x="232"
                    y="20"
                    width="22"
                    height="5"
                    rx="2.5"
                    fill="var(--foreground)"
                    opacity=".35"
                ></rect>
            </g>
            <g class="deco-node">
                <rect
                    x="240"
                    y="52"
                    width="52"
                    height="30"
                    rx="9"
                    fill="color-mix(in srgb, var(--cat-4) 13%, var(--card))"
                    stroke="var(--cat-4)"
                    stroke-width="1.5"
                ></rect>
                <circle cx="255" cy="67" r="4" fill="var(--cat-4)"></circle>
                <rect
                    x="264"
                    y="64"
                    width="22"
                    height="5"
                    rx="2.5"
                    fill="var(--foreground)"
                    opacity=".35"
                ></rect>
            </g>
        </svg>
        <div class="mt-3 flex flex-wrap gap-2">
            <span class="mini-chip"
                ><i style="background: var(--cat-1)"></i>Déclencheurs</span
            >
            <span class="mini-chip"
                ><i style="background: var(--cat-2)"></i>Données</span
            >
            <span class="mini-chip"
                ><i style="background: var(--cat-3)"></i>IA</span
            >
            <span class="mini-chip"
                ><i style="background: var(--cat-4)"></i>Actions</span
            >
            <span class="mini-chip"
                ><i style="background: var(--cat-5)"></i>Logique</span
            >
        </div>
    </div>
</template>

<style lang="scss" scoped>
.deco-svg {
    width: 100%;
    height: auto;
}

.deco-node {
    animation: welcome-float-y 5s ease-in-out infinite;

    &:nth-child(2) {
        animation-delay: -1.4s;
    }

    &:nth-child(3) {
        animation-delay: -2.6s;
    }

    &:nth-child(4) {
        animation-delay: -3.8s;
    }
}

@keyframes welcome-float-y {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-4px);
    }
}

.mini-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 11px;
    border-radius: var(--r-full);
    border: 1px solid var(--border);
    background: var(--card);
    font-size: 11.5px;
    font-weight: 500;
    white-space: nowrap;

    i {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .deco-node {
        animation: none;
    }
}
</style>
