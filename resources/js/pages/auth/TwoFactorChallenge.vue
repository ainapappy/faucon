<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CircleAlert,
    ShieldCheck,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AuthOtpInput from '@/components/auth/AuthOtpInput.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useErrorShake } from '@/composables/useErrorShake';
import { login } from '@/routes';
import { store } from '@/routes/two-factor/login';

defineOptions({
    layout: {
        variant: 'login',
    },
});

const isUsingRecoveryCode = ref(false);
const code = ref('');

const isSubmitting = ref(false);
const hasFailed = ref(false);

const shaking = useErrorShake({
    processing: () => isSubmitting.value,
    hasError: () => hasFailed.value,
});
</script>

<template>
    <Head title="Vérification en deux étapes" />

    <div class="contents">
        <div class="anim-in d-1">
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2"
                data-test="back-to-login-button"
            >
                <Link :href="login()">
                    <ArrowLeft class="size-3.5" />
                    Retour
                </Link>
            </Button>

            <div class="mt-3.5 flex items-center gap-2.5">
                <span
                    class="bg-cat-2/12 text-cat-2 flex size-9.5 shrink-0 items-center justify-center rounded-md"
                >
                    <ShieldCheck class="size-4.5" />
                </span>
                <div>
                    <h2>Vérification en deux étapes</h2>
                    <p class="sub">
                        <template v-if="isUsingRecoveryCode">
                            Saisissez l'un de vos codes de récupération.
                        </template>
                        <template v-else>
                            Saisissez le code à 6 chiffres de votre application
                            d'authentification.
                        </template>
                    </p>
                </div>
            </div>
        </div>

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="grid gap-4.5"
            :class="{ shake: shaking }"
            @start="isSubmitting = true"
            @finish="isSubmitting = false"
            @error="hasFailed = true"
            @success="hasFailed = false"
        >
            <Alert
                v-if="errors.code || errors.recovery_code"
                variant="destructive"
                class="border-destructive/35 bg-destructive/10"
            >
                <CircleAlert />
                <AlertDescription>
                    {{ errors.code ?? errors.recovery_code }}
                </AlertDescription>
            </Alert>

            <template v-if="isUsingRecoveryCode">
                <div class="grid gap-1.75">
                    <Label for="recovery_code">Code de récupération</Label>
                    <Input
                        id="recovery_code"
                        name="recovery_code"
                        required
                        autofocus
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        placeholder="XXXXX-XXXXX"
                    />
                    <InputError :message="errors.recovery_code" />
                </div>

                <p class="text-muted-foreground text-sm">
                    Saisissez l'un de vos codes de récupération d'urgence.
                    <button
                        type="button"
                        class="text-brand-ink underline-offset-3 hover:underline"
                        @click="isUsingRecoveryCode = false"
                        data-test="use-authentication-code-link"
                    >
                        Utiliser un code d'authentification
                    </button>
                    à la place.
                </p>
            </template>

            <template v-else>
                <div class="grid gap-2">
                    <AuthOtpInput
                        v-model="code"
                        :class="{ shake: shaking }"
                        data-test="two-factor-code-input"
                    />
                    <InputError :message="errors.code" />
                </div>

                <p class="text-muted-foreground text-sm">
                    Pas d'appareil sous la main ?
                    <button
                        type="button"
                        class="text-brand-ink underline-offset-3 hover:underline"
                        @click="isUsingRecoveryCode = true"
                        data-test="use-recovery-code-link"
                    >
                        Utiliser un code de récupération
                    </button>
                </p>
            </template>

            <Button
                type="submit"
                size="lg"
                class="bg-brand text-brand-foreground hover:bg-brand-strong w-full"
                :disabled="
                    processing || (!isUsingRecoveryCode && code.length < 6)
                "
                data-test="two-factor-submit-button"
            >
                <Spinner v-if="processing" />
                <Check v-if="!isUsingRecoveryCode" />
                <ArrowRight v-else />
                {{
                    processing
                        ? 'Vérification…'
                        : isUsingRecoveryCode
                          ? 'Se connecter'
                          : 'Vérifier le code'
                }}
            </Button>
        </Form>
    </div>
</template>
