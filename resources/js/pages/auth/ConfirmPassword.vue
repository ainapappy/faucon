<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check, Lock } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useErrorShake } from '@/composables/useErrorShake';
import password from '@/routes/password';

defineOptions({
    layout: {
        variant: 'login',
    },
});

const isSubmitting = ref(false);
const hasFailed = ref(false);

const shaking = useErrorShake({
    processing: () => isSubmitting.value,
    hasError: () => hasFailed.value,
});
</script>

<template>
    <Head title="Confirmation du mot de passe" />

    <div class="contents">
        <div class="anim-in d-1">
            <h2>Zone sécurisée</h2>
            <p class="sub">Confirmez votre mot de passe avant de continuer.</p>
        </div>

        <Form
            v-bind="password.confirm.store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="grid gap-4"
            :class="{ shake: shaking }"
            @start="isSubmitting = true"
            @finish="isSubmitting = false"
            @error="hasFailed = true"
            @success="hasFailed = false"
        >
            <div class="grid gap-1.75">
                <Label for="password">Mot de passe</Label>
                <div class="relative">
                    <Lock
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 z-10 size-3.75 -translate-y-1/2"
                    />
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="pl-9"
                    />
                </div>
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="bg-brand text-brand-foreground hover:bg-brand-strong w-full"
                :tabindex="2"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                <Check v-else />
                {{ processing ? 'Confirmation…' : 'Confirmer' }}
            </Button>
        </Form>
    </div>
</template>
