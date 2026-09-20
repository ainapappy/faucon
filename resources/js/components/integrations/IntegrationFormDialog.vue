<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { KeyRound, Lock } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AUTH_OPTIONS,
    ENCRYPTION_OPTIONS,
    INTEGRATION_TYPE_OPTIONS,
    emptyIntegrationForm,
    hasCredentialInput,
    integrationFormPayload,
    validateIntegrationForm,
} from '@/lib/integrationTypes';
import { store, update } from '@/routes/integrations';
import type { IntegrationSummary } from '@/types';

type Props = {
    /** null = création. En édition, les credentials ne sont JAMAIS pré-remplis. */
    integration: IntegrationSummary | null;
    teamSlug: string;
    open: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const mode = computed(() => (props.integration ? 'edit' : 'create'));

const form = useForm(emptyIntegrationForm());

/*
 * Validation live, miroir des bornes backend : les erreurs d'un champ
 * s'affichent après sa première saisie (blur) ou après une soumission
 * tentée — jamais en criant dès l'ouverture du dialog.
 */
const liveErrors = computed(() =>
    validateIntegrationForm(form.data(), mode.value),
);

const touchedKeys = ref(new Set<string>());
const attemptedSubmit = ref(false);

function markTouched(key: string): void {
    touchedKeys.value.add(key);
}

/*
 * Les clés d'erreur 422 du backend sont plates (« credentials.baseUrl »),
 * hors clés de premier niveau du shape useForm — décast contrôlé et local.
 */
function fieldError(key: string): string | undefined {
    const serverError = (form.errors as Record<string, string | undefined>)[
        key
    ];

    if (serverError) {
        return serverError;
    }

    if (!touchedKeys.value.has(key) && !attemptedSubmit.value) {
        return undefined;
    }

    return liveErrors.value[key];
}

const canSubmit = computed(() => Object.keys(liveErrors.value).length === 0);

const isEdit = computed(() => mode.value === 'edit');

/** En édition, toucher un seul credential impose la re-saisie du jeu complet. */
const credentialsTouched = computed(() => hasCredentialInput(form.data()));

const secretPlaceholder = computed(() =>
    isEdit.value ? 'Inchangé si vide' : undefined,
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.reset();
        form.type = props.integration?.type ?? 'generic_http';
        form.name = props.integration?.name ?? '';
        form.clearErrors();
        touchedKeys.value = new Set<string>();
        attemptedSubmit.value = false;
    },
);

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    attemptedSubmit.value = true;

    if (!canSubmit.value) {
        return;
    }

    // Le type est renvoyé même en update : le backend l'exige (§2.10).
    const payload = integrationFormPayload(form.data(), mode.value);

    if (isEdit.value) {
        form.transform(() => payload).patch(
            update({
                current_team: props.teamSlug,
                integration: props.integration?.id ?? 0,
            }).url,
            {
                onSuccess: () => {
                    toast.success('Intégration mise à jour.');
                    router.reload({ only: ['integrations'] });
                    close();
                },
            },
        );

        return;
    }

    form.transform(() => payload).post(
        store({ current_team: props.teamSlug }).url,
        {
            onSuccess: () => {
                // Redirection index : la liste et le flash toast viennent du serveur.
                close();
            },
        },
    );
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEdit
                            ? 'Modifier l’intégration'
                            : 'Ajouter une intégration'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Les secrets sont chiffrés avant stockage et masqués dans les
                    journaux.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="integration-name">Nom</Label>
                    <Input
                        id="integration-name"
                        v-model="form.name"
                        placeholder="CRM — production"
                        autocomplete="off"
                        data-test="integration-name"
                        @blur="markTouched('name')"
                    />
                    <InputError :message="fieldError('name')" />
                </div>

                <div class="grid gap-2">
                    <Label for="integration-type">Fournisseur</Label>
                    <Select v-model="form.type" :disabled="isEdit">
                        <SelectTrigger
                            id="integration-type"
                            class="w-full"
                            data-test="integration-type"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in INTEGRATION_TYPE_OPTIONS"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Champs : HTTP générique -->
                <template v-if="form.type === 'generic_http'">
                    <div class="grid gap-2">
                        <Label for="integration-baseurl">URL de base</Label>
                        <Input
                            id="integration-baseurl"
                            v-model="form.credentials.baseUrl"
                            class="font-mono"
                            placeholder="https://api.exemple.com"
                            autocomplete="off"
                            data-test="integration-baseurl"
                            @blur="markTouched('credentials.baseUrl')"
                        />
                        <InputError
                            :message="fieldError('credentials.baseUrl')"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="integration-auth">Authentification</Label>
                        <Select v-model="form.credentials.auth">
                            <SelectTrigger
                                id="integration-auth"
                                class="w-full"
                                data-test="integration-auth"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in AUTH_OPTIONS"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="fieldError('credentials.auth')" />
                    </div>

                    <p
                        v-if="isEdit && credentialsTouched"
                        class="text-muted-foreground text-xs"
                    >
                        Toute modification des credentials les remplace
                        intégralement — ressaisissez le jeu complet.
                    </p>

                    <div
                        v-if="form.credentials.auth === 'bearer'"
                        class="grid gap-2"
                    >
                        <Label for="integration-token">Jeton</Label>
                        <div class="relative">
                            <KeyRound
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.75 -translate-y-1/2"
                            />
                            <PasswordInput
                                id="integration-token"
                                v-model="form.credentials.token"
                                class="pl-9"
                                :placeholder="secretPlaceholder ?? 'sk-…'"
                                autocomplete="off"
                                data-test="integration-token"
                                @blur="markTouched('credentials.token')"
                            />
                        </div>
                        <InputError
                            :message="fieldError('credentials.token')"
                        />
                    </div>

                    <template v-if="form.credentials.auth === 'basic'">
                        <div class="grid gap-2">
                            <Label for="integration-username">
                                Nom d’utilisateur
                            </Label>
                            <Input
                                id="integration-username"
                                v-model="form.credentials.username"
                                autocomplete="off"
                                data-test="integration-username"
                                @blur="markTouched('credentials.username')"
                            />
                            <InputError
                                :message="fieldError('credentials.username')"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="integration-password">
                                Mot de passe
                            </Label>
                            <div class="relative">
                                <KeyRound
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.75 -translate-y-1/2"
                                />
                                <PasswordInput
                                    id="integration-password"
                                    v-model="form.credentials.password"
                                    class="pl-9"
                                    :placeholder="secretPlaceholder"
                                    autocomplete="off"
                                    data-test="integration-password"
                                    @blur="markTouched('credentials.password')"
                                />
                            </div>
                            <InputError
                                :message="fieldError('credentials.password')"
                            />
                        </div>
                    </template>

                    <template v-if="form.credentials.auth === 'header'">
                        <div class="grid gap-2">
                            <Label for="integration-headername">
                                Nom d’en-tête
                            </Label>
                            <Input
                                id="integration-headername"
                                v-model="form.credentials.headerName"
                                class="font-mono"
                                placeholder="X-Api-Key"
                                autocomplete="off"
                                data-test="integration-headername"
                                @blur="markTouched('credentials.headerName')"
                            />
                            <InputError
                                :message="fieldError('credentials.headerName')"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="integration-headervalue">
                                Valeur d’en-tête
                            </Label>
                            <div class="relative">
                                <KeyRound
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.75 -translate-y-1/2"
                                />
                                <PasswordInput
                                    id="integration-headervalue"
                                    v-model="form.credentials.headerValue"
                                    class="pl-9"
                                    :placeholder="secretPlaceholder"
                                    autocomplete="off"
                                    data-test="integration-headervalue"
                                    @blur="
                                        markTouched('credentials.headerValue')
                                    "
                                />
                            </div>
                            <InputError
                                :message="fieldError('credentials.headerValue')"
                            />
                        </div>
                    </template>
                </template>

                <!-- Champs : SMTP / E-mail -->
                <template v-else>
                    <p
                        v-if="isEdit && credentialsTouched"
                        class="text-muted-foreground text-xs"
                    >
                        Toute modification des credentials les remplace
                        intégralement — ressaisissez le jeu complet.
                    </p>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-[2fr_1fr]">
                        <div class="grid gap-2">
                            <Label for="integration-host">Hôte</Label>
                            <Input
                                id="integration-host"
                                v-model="form.credentials.host"
                                class="font-mono"
                                placeholder="smtp.exemple.com"
                                autocomplete="off"
                                data-test="integration-host"
                                @blur="markTouched('credentials.host')"
                            />
                            <InputError
                                :message="fieldError('credentials.host')"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="integration-port">Port</Label>
                            <Input
                                id="integration-port"
                                v-model="form.credentials.port"
                                class="font-mono"
                                type="number"
                                min="1"
                                max="65535"
                                placeholder="587"
                                autocomplete="off"
                                data-test="integration-port"
                                @blur="markTouched('credentials.port')"
                            />
                            <InputError
                                :message="fieldError('credentials.port')"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="integration-encryption">Chiffrement</Label>
                        <Select v-model="form.credentials.encryption">
                            <SelectTrigger
                                id="integration-encryption"
                                class="w-full"
                                data-test="integration-encryption"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in ENCRYPTION_OPTIONS"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="fieldError('credentials.encryption')"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="integration-smtp-username">
                                Utilisateur
                            </Label>
                            <Input
                                id="integration-smtp-username"
                                v-model="form.credentials.username"
                                autocomplete="off"
                                data-test="integration-smtp-username"
                                @blur="markTouched('credentials.username')"
                            />
                            <InputError
                                :message="fieldError('credentials.username')"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="integration-smtp-password">
                                Mot de passe
                            </Label>
                            <div class="relative">
                                <KeyRound
                                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.75 -translate-y-1/2"
                                />
                                <PasswordInput
                                    id="integration-smtp-password"
                                    v-model="form.credentials.password"
                                    class="pl-9"
                                    :placeholder="secretPlaceholder"
                                    autocomplete="off"
                                    data-test="integration-smtp-password"
                                    @blur="markTouched('credentials.password')"
                                />
                            </div>
                            <InputError
                                :message="fieldError('credentials.password')"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="integration-from">
                            Adresse expéditeur (optionnelle)
                        </Label>
                        <Input
                            id="integration-from"
                            v-model="form.credentials.from"
                            placeholder="no-reply@exemple.com"
                            autocomplete="off"
                            data-test="integration-from"
                            @blur="markTouched('credentials.from')"
                        />
                        <InputError :message="fieldError('credentials.from')" />
                    </div>
                </template>

                <Alert class="border-info/25 bg-info-soft">
                    <Lock class="text-info size-4" />
                    <AlertDescription>
                        Chiffrement AES-256 au repos · secrets masqués
                        automatiquement dans les logs d’exécution.
                    </AlertDescription>
                </Alert>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        data-test="integration-cancel"
                        @click="close"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !canSubmit"
                        data-test="integration-save-button"
                    >
                        <Lock v-if="!isEdit" class="size-4" />
                        {{ isEdit ? 'Enregistrer' : 'Chiffrer et enregistrer' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
