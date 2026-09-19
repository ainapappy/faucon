<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/two-factor/login';

defineOptions({
    layout: {
        title: 'Two-factor authentication',
        description:
            'Confirm access to your account by entering the authentication code provided by your authenticator application',
    },
});

const isUsingRecoveryCode = ref(false);
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-6">
        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <template v-if="isUsingRecoveryCode">
                    <div class="grid gap-2">
                        <Label for="recovery_code">Recovery code</Label>
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

                    <div class="text-muted-foreground text-sm">
                        <span
                            >Enter one of your emergency recovery codes.
                        </span>
                        <button
                            type="button"
                            class="hover:text-foreground underline underline-offset-4"
                            @click="isUsingRecoveryCode = false"
                            data-test="use-authentication-code-link"
                        >
                            Use an authentication code
                        </button>
                        <span> instead.</span>
                    </div>
                </template>

                <template v-else>
                    <div class="grid gap-2">
                        <Label for="code">Authentication code</Label>
                        <Input
                            id="code"
                            name="code"
                            required
                            autofocus
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            placeholder="123456"
                            data-test="two-factor-code-input"
                        />
                        <InputError :message="errors.code" />
                    </div>

                    <div class="text-muted-foreground text-sm">
                        <span> Lost access to your authenticator app? </span
                        ><button
                            type="button"
                            class="hover:text-foreground underline underline-offset-4"
                            @click="isUsingRecoveryCode = true"
                            data-test="use-recovery-code-link"
                        >
                            Use a recovery code
                        </button>
                        <span> to sign in.</span>
                    </div>
                </template>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="two-factor-submit-button"
                >
                    <Spinner v-if="processing" />
                    Log in
                </Button>
            </div>
        </Form>
    </div>
</template>
