<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { ArrowRight, CircleAlert, Fingerprint, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useErrorShake } from '@/composables/useErrorShake';
import { register } from '@/routes';
import { login as passkeyLogin, loginOptions } from '@/routes/passkey';
import { request } from '@/routes/password';
import { store } from '@/routes/login';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        variant: 'login',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();

const rememberMe = ref(true);

const {
    verify: verifyPasskey,
    isLoading: passkeyLoading,
    error: passkeyError,
    errorInstance: passkeyErrorInstance,
    isSupported: passkeySupported,
} = usePasskeyVerify({
    remember: () => rememberMe.value,
    routes: {
        options: loginOptions.url(),
        submit: passkeyLogin.url(),
    },
    onSuccess: (response) => {
        if (response.redirect) {
            router.visit(response.redirect);
        }
    },
});

// A cancelled browser prompt is a no-op, not an error worth showing.
const passkeyErrorMessage = computed(() =>
    passkeyErrorInstance.value instanceof UserCancelledError
        ? null
        : passkeyError.value,
);

const isSubmitting = ref(false);
const hasFailed = ref(false);
const shaking = useErrorShake({
    processing: () => isSubmitting.value,
    hasError: () => hasFailed.value,
});
</script>

<template>
    <Head title="Connexion" />

    <div class="contents">
        <div
            v-if="status"
            role="status"
            class="text-success text-center text-sm font-medium"
        >
            {{ status }}
        </div>

        <TeamInvitationAlert
            v-if="teamInvitation"
            :invitation="teamInvitation"
            action="Log in"
        />

        <div class="anim-in d-1">
            <h2>Content de vous revoir</h2>
            <p class="sub">Connectez-vous pour retrouver vos workflows.</p>
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="grid gap-4"
            :class="{ shake: shaking }"
            @start="isSubmitting = true"
            @finish="isSubmitting = false"
            @error="hasFailed = true"
            @success="hasFailed = false"
        >
            <Alert
                v-if="errors.email"
                variant="destructive"
                class="border-destructive/35 bg-destructive/10"
            >
                <CircleAlert />
                <AlertDescription>{{ errors.email }}</AlertDescription>
            </Alert>

            <div class="grid gap-1.75">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="vous@exemple.com"
                />
            </div>

            <div class="grid gap-1.75">
                <div class="flex items-center justify-between">
                    <Label for="password">Mot de passe</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-[12.5px]"
                        :tabindex="5"
                    >
                        Mot de passe oublié ?
                    </TextLink>
                </div>
                <div class="relative">
                    <Lock
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 z-10 size-3.75 -translate-y-1/2"
                    />
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="pl-9"
                    />
                </div>
                <InputError :message="errors.password" />
            </div>

            <Label
                for="remember"
                class="flex cursor-pointer items-center gap-2.25 text-[13.5px] font-normal"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    :checked="rememberMe"
                    @update:checked="rememberMe = $event === true"
                />
                Rester connecté 30 jours
            </Label>

            <Button
                type="submit"
                size="lg"
                class="bg-brand text-brand-foreground hover:bg-brand-strong w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <ArrowRight v-else />
                {{ processing ? 'Connexion…' : 'Se connecter' }}
            </Button>
        </Form>

        <div
            v-if="passkeySupported"
            class="text-muted-foreground flex items-center gap-3 text-xs"
        >
            <Separator class="flex-1" />
            ou
            <Separator class="flex-1" />
        </div>

        <template v-if="passkeySupported">
            <Button
                variant="outline"
                class="w-full"
                :disabled="passkeyLoading"
                data-test="passkey-login-button"
                @click="verifyPasskey"
            >
                <Spinner v-if="passkeyLoading" />
                <Fingerprint v-else />
                Continuer avec une clé d'accès
            </Button>

            <p
                v-if="passkeyErrorMessage"
                class="text-destructive text-center text-sm"
            >
                {{ passkeyErrorMessage }}
            </p>
        </template>

        <p class="auth-alt">
            Pas encore de compte ?
            <TextLink :href="register()" :tabindex="6">
                Créez-en un gratuitement
            </TextLink>
        </p>
    </div>
</template>
