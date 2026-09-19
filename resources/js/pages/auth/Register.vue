<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleAlert, Lock, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useErrorShake } from '@/composables/useErrorShake';
import {
    usePasswordMatch,
    usePasswordStrength,
} from '@/composables/usePasswordValidation';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        variant: 'register',
    },
});

const password = ref('');
const confirmation = ref('');

const { strength } = usePasswordStrength(password);
const { match } = usePasswordMatch(password, confirmation);

const isSubmitting = ref(false);
const hasFailed = ref(false);

const shaking = useErrorShake({
    processing: () => isSubmitting.value,
    hasError: () => hasFailed.value,
});
</script>

<template>
    <Head title="Créer un compte" />

    <div class="contents">
        <div class="anim-in d-1">
            <h2>Créez votre compte</h2>
            <p class="sub">Votre espace d'équipe est créé automatiquement.</p>
        </div>

        <Form
            v-bind="store.form()"
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
                <Label for="name">Nom complet</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    placeholder="Aina Papy"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.75">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    placeholder="vous@exemple.com"
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
                        :tabindex="3"
                        autocomplete="new-password"
                        placeholder="12 caractères minimum"
                        class="pl-9"
                        v-model="password"
                    />
                </div>
                <div
                    class="strength"
                    data-test="strength-meter"
                    aria-hidden="true"
                >
                    <i
                        v-for="i in 4"
                        :key="i"
                        :class="{ filled: strength.score >= i }"
                        :style="
                            strength.score >= i
                                ? { '--strength-color': strength.color }
                                : undefined
                        "
                    />
                </div>
                <span class="field-hint">
                    Force :
                    <b :style="{ color: strength.color }">{{
                        strength.label
                    }}</b>
                    <span v-if="password">
                        · {{ password.length }} caractères
                    </span>
                </span>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.75">
                <Label for="password_confirmation">
                    Confirmer le mot de passe
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    :class="
                        confirmation && !match
                            ? 'border-destructive ring-destructive/15'
                            : undefined
                    "
                    v-model="confirmation"
                />
                <span
                    v-if="confirmation && !match"
                    class="text-destructive flex items-center gap-1.25 text-[12.5px]"
                >
                    <CircleAlert class="size-3.25" />
                    Les mots de passe ne correspondent pas
                </span>
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="terms"
                    class="flex cursor-pointer items-start gap-2.25 text-[13px] leading-normal font-normal"
                >
                    <Checkbox
                        id="terms"
                        name="terms"
                        :tabindex="5"
                        data-test="terms-checkbox"
                        class="mt-0.5"
                    />
                    <span>
                        J'accepte les
                        <a
                            href="#"
                            class="text-brand-ink font-medium underline-offset-3 hover:underline"
                        >
                            conditions d'utilisation
                        </a>
                        et la
                        <a
                            href="#"
                            class="text-brand-ink font-medium underline-offset-3 hover:underline"
                        >
                            politique de confidentialité </a
                        >.
                    </span>
                </Label>
                <InputError :message="errors.terms" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="bg-brand text-brand-foreground hover:bg-brand-strong w-full"
                :tabindex="6"
                :disabled="processing || !match"
                data-test="register-button"
            >
                <Spinner v-if="processing" />
                <UserPlus v-else />
                {{ processing ? 'Création…' : 'Créer mon compte' }}
            </Button>
        </Form>

        <p class="auth-alt">
            Déjà inscrit ?
            <TextLink :href="login()" :tabindex="7">Se connecter</TextLink>
        </p>
    </div>
</template>
