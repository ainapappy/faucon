<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, MailCheck, Send } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        variant: 'register',
    },
});

defineProps<{
    status?: string;
}>();

const page = usePage();
const email = page.props.auth.user.email;
</script>

<template>
    <Head title="Vérifiez votre boîte mail" />

    <div class="contents">
        <div
            v-if="status === 'verification-link-sent'"
            role="status"
            class="border-success/35 bg-success-soft text-success flex items-center gap-3 rounded-md border px-4 py-3 text-sm"
        >
            <CircleCheck class="size-4 shrink-0" />
            Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
        </div>

        <div class="anim-in d-1 py-5 text-center">
            <div
                class="bg-success-soft text-success anim-pop mx-auto mb-4.5 flex size-16 items-center justify-center rounded-full"
            >
                <MailCheck class="size-7" />
                <span class="sr-only">E-mail envoyé</span>
            </div>

            <h2>Vérifiez votre boîte mail</h2>
            <p class="sub mx-auto mt-2 max-w-[34ch]">
                Un lien de vérification a été envoyé à
                <b class="text-foreground font-semibold">{{ email }}</b
                >. Il expire dans 60 minutes.
            </p>

            <div class="mt-5.5 grid gap-2.5">
                <Form v-bind="send.form()" v-slot="{ processing }">
                    <Button
                        variant="outline"
                        class="w-full"
                        :disabled="processing"
                        data-test="resend-button"
                    >
                        <Spinner v-if="processing" />
                        <Send v-else />
                        {{ processing ? 'Envoi…' : 'Renvoyer l’e-mail' }}
                    </Button>
                </Form>

                <Button variant="ghost" as-child class="w-full">
                    <Link :href="logout()">Retour à la connexion</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
