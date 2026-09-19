<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleAlert, KeyRound, Lock } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useErrorShake } from '@/composables/useErrorShake';
import { usePasswordMatch } from '@/composables/usePasswordValidation';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        variant: 'login',
    },
});

defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const password = ref('');
const confirmation = ref('');

const { match } = usePasswordMatch(password, confirmation);

const isSubmitting = ref(false);
const hasFailed = ref(false);

const shaking = useErrorShake({
    processing: () => isSubmitting.value,
    hasError: () => hasFailed.value,
});
</script>

<template>
    <Head title="Réinitialisation du mot de passe" />

    <div class="contents">
        <div class="anim-in d-1">
            <h2>Nouveau mot de passe</h2>
            <p class="sub">Définissez votre nouveau mot de passe ci-dessous.</p>
        </div>

        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="grid gap-3.75"
            :class="{ shake: shaking }"
            @start="isSubmitting = true"
            @finish="isSubmitting = false"
            @error="hasFailed = true"
            @success="hasFailed = false"
        >
            <div class="grid gap-1.75">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :model-value="email"
                    :tabindex="-1"
                    autocomplete="email"
                    readonly
                />
                <InputError :message="errors.email" />
            </div>

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
                        autocomplete="new-password"
                        placeholder="12 caractères minimum"
                        class="pl-9"
                        :passwordrules="passwordRules"
                        v-model="password"
                    />
                </div>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.75">
                <Label for="password_confirmation">
                    Confirmer le mot de passe
                </Label>
                <div class="relative">
                    <Lock
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 z-10 size-3.75 -translate-y-1/2"
                    />
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        :tabindex="2"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="pl-9"
                        :class="
                            confirmation && !match
                                ? 'border-destructive ring-destructive/15'
                                : undefined
                        "
                        :passwordrules="passwordRules"
                        v-model="confirmation"
                    />
                </div>
                <span
                    v-if="confirmation && !match"
                    class="text-destructive flex items-center gap-1.25 text-[12.5px]"
                >
                    <CircleAlert class="size-3.25" />
                    Les mots de passe ne correspondent pas
                </span>
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="bg-brand text-brand-foreground hover:bg-brand-strong w-full"
                :tabindex="3"
                :disabled="processing || !match"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                <KeyRound v-else />
                {{
                    processing
                        ? 'Réinitialisation…'
                        : 'Réinitialiser le mot de passe'
                }}
            </Button>
        </Form>
    </div>
</template>
