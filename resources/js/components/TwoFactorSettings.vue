<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Check, Copy, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    confirm,
    disable,
    enable,
    qrCode,
    recoveryCodes,
    regenerateRecoveryCodes,
    secretKey,
} from '@/routes/two-factor';

type Props = {
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
};

const props = defineProps<Props>();

// Held client-side on purpose: after an enable that still requires confirmation,
// the server keeps twoFactorEnabled false (and auto-discards an unconfirmed
// setup on the next page load), so this step cannot come from the props.
const isConfirming = ref(false);

type TwoFactorState = 'off' | 'confirming' | 'on';

const state = computed<TwoFactorState>(() => {
    if (props.twoFactorEnabled) {
        return 'on';
    }

    return isConfirming.value ? 'confirming' : 'off';
});

const qrCodeSvg = ref<string | null>(null);
const secretKeyValue = ref<string | null>(null);
const recoveryCodesValue = ref<string[] | null>(null);
const isLoadingSetup = ref(false);
const isLoadingCodes = ref(false);

async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return (await response.json()) as T;
}

async function loadSetupDetails(): Promise<void> {
    isLoadingSetup.value = true;

    try {
        const [qr, secret] = await Promise.all([
            fetchJson<Record<string, unknown>>(qrCode.url()),
            fetchJson<Record<string, unknown>>(secretKey.url()),
        ]);

        qrCodeSvg.value = typeof qr.svg === 'string' ? qr.svg : null;
        secretKeyValue.value =
            typeof secret.secretKey === 'string' ? secret.secretKey : null;
    } catch {
        toast.error(
            'Unable to load the two factor authentication setup details.',
        );
    } finally {
        isLoadingSetup.value = false;
    }
}

async function loadRecoveryCodes(): Promise<void> {
    isLoadingCodes.value = true;

    try {
        const codes = await fetchJson<unknown>(recoveryCodes.url());
        recoveryCodesValue.value = Array.isArray(codes)
            ? (codes as string[])
            : null;
    } catch {
        toast.error('Unable to load your recovery codes.');
    } finally {
        isLoadingCodes.value = false;
    }
}

watch(
    state,
    (value) => {
        if (value === 'confirming') {
            void loadSetupDetails();
        }

        if (value === 'on') {
            void loadRecoveryCodes();
        }
    },
    { immediate: true },
);

function onEnabled(): void {
    if (props.requiresConfirmation) {
        isConfirming.value = true;
    }

    router.reload();
}

function onConfirmed(): void {
    isConfirming.value = false;
    router.reload();
}

function onDisabled(): void {
    isConfirming.value = false;
    recoveryCodesValue.value = null;
    router.reload();
}

const hasCopiedSecret = ref(false);
const hasCopiedCodes = ref(false);

async function copyToClipboard(
    value: string,
    marker: Ref<boolean>,
): Promise<void> {
    try {
        await navigator.clipboard.writeText(value);
        marker.value = true;
        setTimeout(() => {
            marker.value = false;
        }, 2000);
    } catch {
        toast.error('Unable to copy to your clipboard.');
    }
}

function copySecretKey(): void {
    if (secretKeyValue.value) {
        void copyToClipboard(secretKeyValue.value, hasCopiedSecret);
    }
}

function copyRecoveryCodes(): void {
    if (recoveryCodesValue.value) {
        void copyToClipboard(
            recoveryCodesValue.value.join('\n'),
            hasCopiedCodes,
        );
    }
}
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Two factor authentication"
            description="Add an extra layer of security to your account using an authenticator application"
        />

        <div
            v-if="state === 'off'"
            class="flex items-center justify-between gap-4"
        >
            <p class="text-muted-foreground text-sm">
                Two factor authentication is not enabled yet.
            </p>

            <Form
                v-bind="enable.form()"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
                @success="onEnabled"
            >
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="enable-two-factor-button"
                >
                    <Spinner v-if="processing" />
                    Enable
                </Button>
            </Form>
        </div>

        <div v-else-if="state === 'confirming'" class="space-y-6">
            <p class="text-sm">
                Finish enabling two factor authentication by scanning the QR
                code below with your authenticator application, then confirm
                with a generated code.
            </p>

            <div class="flex items-start gap-6">
                <div
                    v-if="qrCodeSvg"
                    class="rounded-md border bg-white p-3 [&_svg]:size-40"
                    v-html="qrCodeSvg"
                />
                <div
                    v-else-if="isLoadingSetup"
                    class="size-46 animate-pulse rounded-md bg-neutral-200 dark:bg-neutral-700"
                />

                <div class="grid gap-2">
                    <p class="text-muted-foreground text-sm">
                        Or, manually enter the setup key in your authenticator
                        application:
                    </p>

                    <div v-if="secretKeyValue" class="flex items-center gap-2">
                        <code
                            class="overflow-x-auto font-mono text-sm break-all"
                        >
                            {{ secretKeyValue }}
                        </code>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Copy setup key"
                            data-test="copy-two-factor-secret-button"
                            @click="copySecretKey"
                        >
                            <Check
                                v-if="hasCopiedSecret"
                                class="text-green-600"
                            />
                            <Copy v-else />
                        </Button>
                    </div>
                    <div
                        v-else-if="isLoadingSetup"
                        class="h-5 w-40 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"
                    />
                </div>
            </div>

            <Form
                v-bind="confirm.form()"
                error-bag="confirmTwoFactorAuthentication"
                :options="{ preserveScroll: true }"
                reset-on-success
                v-slot="{ errors, processing }"
                class="grid max-w-sm gap-4"
                @success="onConfirmed"
            >
                <div class="grid gap-2">
                    <Label for="two-factor-code">Authentication code</Label>
                    <Input
                        id="two-factor-code"
                        name="code"
                        required
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        data-test="confirm-two-factor-code-input"
                    />
                    <InputError :message="errors.code" />
                </div>

                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="confirm-two-factor-button"
                >
                    <Spinner v-if="processing" />
                    Confirm
                </Button>
            </Form>
        </div>

        <div v-else class="space-y-6">
            <div class="flex items-center gap-2 text-sm">
                <ShieldCheck class="size-4 text-green-600" />
                <p>
                    Two factor authentication is enabled. Keep your recovery
                    codes in a safe place.
                </p>
            </div>

            <div class="grid gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">Recovery codes</p>
                    <Button
                        variant="ghost"
                        size="sm"
                        data-test="copy-recovery-codes-button"
                        :disabled="!recoveryCodesValue"
                        @click="copyRecoveryCodes"
                    >
                        <Check v-if="hasCopiedCodes" class="text-green-600" />
                        <Copy v-else />
                        Copy codes
                    </Button>
                </div>

                <div
                    v-if="recoveryCodesValue"
                    class="grid grid-cols-2 gap-x-6 gap-y-1 rounded-md border p-4 sm:grid-cols-3"
                    data-test="recovery-codes-list"
                >
                    <code
                        v-for="code in recoveryCodesValue"
                        :key="code"
                        class="font-mono text-sm"
                    >
                        {{ code }}
                    </code>
                </div>
                <div
                    v-else-if="isLoadingCodes"
                    class="grid grid-cols-2 gap-x-6 gap-y-2 rounded-md border p-4 sm:grid-cols-3"
                >
                    <div
                        v-for="index in 8"
                        :key="index"
                        class="h-4 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"
                    />
                </div>

                <Form
                    v-bind="regenerateRecoveryCodes.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                    class="w-fit"
                    @success="loadRecoveryCodes"
                >
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="processing"
                        data-test="regenerate-recovery-codes-button"
                    >
                        <Spinner v-if="processing" />
                        Regenerate codes
                    </Button>
                </Form>
            </div>

            <div class="flex items-center justify-between gap-4 border-t pt-6">
                <p class="text-muted-foreground text-sm">
                    Disable two factor authentication and remove its recovery
                    codes.
                </p>

                <Form
                    v-bind="disable.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                    @success="onDisabled"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                        data-test="disable-two-factor-button"
                    >
                        <Spinner v-if="processing" />
                        Disable
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
