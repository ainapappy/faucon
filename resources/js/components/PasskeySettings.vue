<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { KeyRound, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, registrationOptions, store } from '@/routes/passkey';
import type { Passkey } from '@/types';

type Props = {
    passkeys?: Passkey[];
};

const props = defineProps<Props>();

const {
    register: registerPasskey,
    isLoading: isRegistering,
    error: registerError,
    errorInstance: registerErrorInstance,
    isSupported: isPasskeySupported,
} = usePasskeyRegister({
    routes: {
        options: registrationOptions.url(),
        submit: store.url(),
    },
    onSuccess: () => {
        toast.success('Passkey registered.');
        closeAddDialog();
        router.reload();
    },
});

const isAddDialogOpen = ref(false);
const passkeyName = ref('');

function closeAddDialog(): void {
    isAddDialogOpen.value = false;
    passkeyName.value = '';
}

async function submitPasskey(): Promise<void> {
    await registerPasskey(passkeyName.value.trim());
}

// A cancelled browser prompt is a no-op, not an error worth showing.
const registerErrorMessage = computed(() =>
    registerErrorInstance.value instanceof UserCancelledError
        ? null
        : registerError.value,
);

const deleteHttp = useHttp();
const isDeleteDialogOpen = ref(false);
const passkeyToDelete = ref<Passkey | null>(null);
const isDeleting = ref(false);

function openDeleteDialog(passkey: Passkey): void {
    passkeyToDelete.value = passkey;
    isDeleteDialogOpen.value = true;
}

function closeDeleteDialog(): void {
    isDeleteDialogOpen.value = false;
    passkeyToDelete.value = null;
}

function deletePasskey(): void {
    if (!passkeyToDelete.value) {
        return;
    }

    isDeleting.value = true;
    deleteHttp.delete(destroy(passkeyToDelete.value.id).url, {
        onSuccess: () => {
            isDeleting.value = false;
            toast.success('Passkey deleted.');
            closeDeleteDialog();
            router.reload();
        },
        onError: () => {
            isDeleting.value = false;
            toast.error('Unable to delete this passkey.');
        },
    });
}

function formatLastUsedAt(lastUsedAt: string | null): string {
    if (!lastUsedAt) {
        return 'Never used';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(lastUsedAt));
}
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Passkeys"
            description="Sign in without a password using your device's built-in authentication"
        />

        <p v-if="!isPasskeySupported" class="text-muted-foreground text-sm">
            Your browser does not support passkeys.
        </p>

        <div v-else class="space-y-4">
            <ul
                v-if="props.passkeys && props.passkeys.length > 0"
                class="divide-y rounded-md border"
                data-test="passkey-list"
            >
                <li
                    v-for="passkey in props.passkeys"
                    :key="passkey.id"
                    class="flex items-center justify-between gap-4 p-4"
                >
                    <div class="flex items-center gap-3">
                        <KeyRound class="text-muted-foreground size-4" />
                        <div class="grid gap-0.5">
                            <p class="text-sm font-medium">
                                {{ passkey.name }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                {{ formatLastUsedAt(passkey.lastUsedAt) }}
                            </p>
                        </div>
                    </div>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Delete passkey"
                        data-test="delete-passkey-button"
                        @click="openDeleteDialog(passkey)"
                    >
                        <Trash2 />
                    </Button>
                </li>
            </ul>

            <p v-else class="text-muted-foreground text-sm">
                No passkeys yet. Add one to sign in without your password.
            </p>

            <Dialog
                :open="isAddDialogOpen"
                @update:open="isAddDialogOpen = $event"
            >
                <DialogTrigger as-child>
                    <Button data-test="add-passkey-button">
                        <Plus />
                        Add passkey
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <form @submit.prevent="submitPasskey">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Add passkey</DialogTitle>
                            <DialogDescription>
                                Name this device so you can recognize it later.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2 py-4">
                            <Label for="passkey-name">Name</Label>
                            <Input
                                id="passkey-name"
                                v-model="passkeyName"
                                required
                                autocomplete="off"
                                placeholder="Work laptop"
                            />
                            <p
                                v-if="registerErrorMessage"
                                class="text-sm text-red-600 dark:text-red-500"
                            >
                                {{ registerErrorMessage }}
                            </p>
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    @click="closeAddDialog"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                :disabled="isRegistering || !passkeyName.trim()"
                            >
                                <Spinner v-if="isRegistering" />
                                Add
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="isDeleteDialogOpen"
                @update:open="isDeleteDialogOpen = $event"
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Delete "{{ passkeyToDelete?.name }}"?
                        </DialogTitle>
                        <DialogDescription>
                            This device will no longer be able to sign in to
                            your account with this passkey.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="secondary"
                                @click="closeDeleteDialog"
                            >
                                Cancel
                            </Button>
                        </DialogClose>

                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="isDeleting"
                            data-test="confirm-delete-passkey-button"
                            @click="deletePasskey"
                        >
                            <Spinner v-if="isDeleting" />
                            Delete passkey
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
