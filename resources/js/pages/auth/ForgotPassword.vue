<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MailCheck, Send } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useErrorShake } from '@/composables/useErrorShake';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        variant: 'login',
    },
});

defineProps<{
    status?: string;
}>();

const isSubmitting = ref(false);
const hasFailed = ref(false);

const shaking = useErrorShake({
    processing: () => isSubmitting.value,
    hasError: () => hasFailed.value,
});
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div class="contents">
        <div class="anim-in d-1">
            <h2>Mot de passe oublié</h2>
            <p class="sub">
                Entrez votre adresse e-mail pour recevoir un lien de
                réinitialisation.
            </p>
        </div>

        <div
            v-if="status"
            role="status"
            class="border-success/35 bg-success-soft text-success flex items-center gap-3 rounded-md border px-4 py-3 text-sm"
        >
            <MailCheck class="size-4 shrink-0" />
            Nous vous avons envoyé le lien de réinitialisation par e-mail.
        </div>

        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="grid gap-4"
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
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="off"
                    placeholder="vous@exemple.com"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="bg-brand text-brand-foreground hover:bg-brand-strong w-full"
                :tabindex="2"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                <Send v-else />
                {{ processing ? 'Envoi…' : 'Envoyer le lien' }}
            </Button>
        </Form>

        <p class="auth-alt">
            Ou, retour à la
            <TextLink :href="login()" :tabindex="3">connexion</TextLink>
        </p>
    </div>
</template>
