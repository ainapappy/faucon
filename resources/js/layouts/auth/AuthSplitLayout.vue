<script setup lang="ts">
import AuthBrandPanel from '@/components/auth/AuthBrandPanel.vue';
import type { AuthBrandVariant } from '@/types';

withDefaults(
    defineProps<{
        variant?: AuthBrandVariant;
    }>(),
    { variant: 'login' },
);
</script>

<template>
    <div class="auth-split">
        <AuthBrandPanel :variant="variant" />

        <section class="auth-form-side">
            <div class="auth-form">
                <slot />
            </div>
        </section>
    </div>
</template>
