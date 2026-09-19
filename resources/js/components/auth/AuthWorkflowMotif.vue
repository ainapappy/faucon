<script setup lang="ts">
withDefaults(
    defineProps<{
        conditionTags?: boolean;
    }>(),
    { conditionTags: false },
);
</script>

<template>
    <svg
        class="auth-motif"
        viewBox="0 0 320 120"
        fill="none"
        aria-hidden="true"
    >
        <path class="motif-edge" d="M62 42 C 80 42, 74 88, 92 88" />
        <path
            class="motif-edge"
            d="M148 88 C 166 88, 160 42, 178 42"
            style="animation-delay: 0.18s"
        />
        <path
            class="motif-edge"
            d="M234 42 C 250 42, 240 88, 256 88"
            style="animation-delay: 0.36s"
        />
        <path
            class="motif-edge"
            d="M234 42 C 250 42, 240 18, 256 18"
            style="animation-delay: 0.5s"
        />
        <rect class="motif-node" x="6" y="30" width="56" height="24" rx="7" />
        <rect class="motif-node" x="92" y="76" width="56" height="24" rx="7" />
        <rect class="motif-node" x="178" y="30" width="56" height="24" rx="7" />
        <rect class="motif-node" x="256" y="76" width="56" height="24" rx="7" />
        <rect class="motif-node" x="256" y="6" width="56" height="24" rx="7" />
        <text class="motif-label" x="34" y="45" text-anchor="middle">
            Webhook
        </text>
        <text class="motif-label" x="120" y="91" text-anchor="middle">
            IA · classe
        </text>
        <text class="motif-label" x="206" y="45" text-anchor="middle">
            Condition
        </text>
        <text class="motif-label" x="284" y="91" text-anchor="middle">
            Email
        </text>
        <text class="motif-label" x="284" y="21" text-anchor="middle">
            HTTP
        </text>
        <template v-if="conditionTags">
            <text class="motif-tag" x="248" y="40" text-anchor="middle">
                true
            </text>
            <text class="motif-tag" x="248" y="56" text-anchor="middle">
                false
            </text>
        </template>
    </svg>
</template>
