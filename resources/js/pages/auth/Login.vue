<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { Fingerprint } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { login as passkeyLogin, loginOptions } from '@/routes/passkey';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();

const rememberMe = ref(false);

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
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Log in"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        :checked="rememberMe"
                        @update:checked="rememberMe = $event === true"
                    />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>
    </Form>

    <template v-if="passkeySupported">
        <div class="flex items-center gap-3">
            <Separator class="flex-1" />
            <span class="text-muted-foreground text-xs uppercase">Or</span>
            <Separator class="flex-1" />
        </div>

        <div class="flex flex-col gap-2">
            <Button
                variant="outline"
                class="w-full"
                :disabled="passkeyLoading"
                data-test="passkey-login-button"
                @click="verifyPasskey"
            >
                <Spinner v-if="passkeyLoading" />
                <Fingerprint v-else />
                Log in with a passkey
            </Button>

            <p
                v-if="passkeyErrorMessage"
                class="text-center text-sm text-red-600 dark:text-red-500"
            >
                {{ passkeyErrorMessage }}
            </p>
        </div>
    </template>
</template>
