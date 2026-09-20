<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Info,
    RefreshCw,
    TriangleAlert,
    Webhook,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useClipboardCopy } from '@/composables/useClipboardCopy';
import workflows from '@/routes/workflows';

/** Réponse JSON des endpoints webhook-url / webhook-regenerate (D20). */
type WebhookUrlResponse = { url: string | null };

type Props = {
    /** Workflow édité — les endpoints webhook sont workflow-scoped (D20). */
    workflowId: number;
    /** La régénération exige `workflow:update` (D20) ; lecture/copie restent accessibles. */
    canUpdateWorkflow: boolean;
};

const props = defineProps<Props>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

/*
 * Machine d'états de la récupération de l'URL (GET workflows.webhook.url,
 * Policy view) : loading → ready (url, ou null = le graphe enregistré n'a
 * pas encore de trigger webhook) | error (réessayable). L'URL ne vit QUE
 * dans cet état local — jamais dans les props de page, jamais logguée,
 * jamais dans le graphe sauvegardé (D3/D20).
 */
type UrlState = 'loading' | 'ready' | 'error';

const state = ref<UrlState>('loading');
const url = ref<string | null>(null);
const { copied, copy } = useClipboardCopy();

const regenerateDialogOpen = ref(false);
const regenerating = ref(false);

/* Données vides : un GET ne doit projeter aucun paramètre en query string. */
const urlHttp = useHttp<Record<string, never>, WebhookUrlResponse>();
const regenerateHttp = useHttp<Record<string, never>, WebhookUrlResponse>();

function webhookUrlEndpoint(): string {
    return workflows.webhook.url({
        current_team: teamSlug.value,
        workflow: props.workflowId,
    }).url;
}

function webhookRegenerateEndpoint(): string {
    return workflows.webhook.regenerate({
        current_team: teamSlug.value,
        workflow: props.workflowId,
    }).url;
}

async function load(): Promise<void> {
    state.value = 'loading';
    try {
        const response = await urlHttp.get(webhookUrlEndpoint());
        url.value = response.url;
        state.value = 'ready';
    } catch {
        state.value = 'error';
    }
}

onMounted(() => {
    void load();
});

async function copyUrl(): Promise<void> {
    if (!url.value) {
        return;
    }
    const succeeded = await copy(url.value);
    if (!succeeded) {
        toast.error('Copie impossible');
    }
}

/* Fermeture gardée : pas de fermeture pendant la régénération en vol. */
function setDialogOpen(open: boolean): void {
    if (!open && regenerating.value) {
        return;
    }
    regenerateDialogOpen.value = open;
}

async function confirmRegenerate(): Promise<void> {
    if (regenerating.value) {
        return;
    }
    regenerating.value = true;
    try {
        const response = await regenerateHttp.post(
            webhookRegenerateEndpoint(),
            {
                // 422 : messages du bag d'erreurs, la dialog reste ouverte.
                onError: (errors) => {
                    toast.error('Régénération impossible', {
                        description: Object.values(errors)
                            .flat()
                            .slice(0, 3)
                            .join(' '),
                    });
                },
            },
        );
        if (response) {
            url.value = response.url;
            regenerateDialogOpen.value = false;
            toast.success('Token régénéré', {
                description: 'L’ancienne URL n’est plus valide.',
            });
        }
    } catch {
        toast.error('Régénération impossible', {
            description: 'Vérifiez votre connexion puis réessayez.',
        });
    } finally {
        regenerating.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4" data-test="webhook-inspector-section">
        <Separator />

        <!-- URL publique : mono, tronquée, copiable -->
        <div class="grid gap-2">
            <Label for="webhook-url">URL publique</Label>

            <Skeleton
                v-if="state === 'loading'"
                class="h-9 w-full"
                data-test="webhook-url-loading"
            />

            <template v-else-if="state === 'error'">
                <Alert variant="destructive" class="text-[12.5px]">
                    <TriangleAlert />
                    <AlertDescription>
                        Impossible de récupérer l’URL du webhook.
                    </AlertDescription>
                </Alert>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full"
                    data-test="webhook-url-retry"
                    @click="load"
                >
                    <RefreshCw class="size-3.5" /> Réessayer
                </Button>
            </template>

            <template v-else-if="url">
                <div class="relative flex items-center">
                    <Webhook
                        class="text-muted-foreground pointer-events-none absolute left-2.75 size-3.75"
                    />
                    <Input
                        id="webhook-url"
                        :model-value="url"
                        readonly
                        class="pr-8.5 pl-8.5 font-mono text-xs"
                        aria-label="URL publique du webhook"
                        data-test="webhook-url-input"
                    />
                    <Button
                        variant="ghost"
                        size="icon"
                        class="absolute right-1.5 h-7 w-7"
                        :aria-label="copied ? 'Copié' : 'Copier'"
                        data-test="webhook-copy"
                        @click="copyUrl"
                    >
                        <Check v-if="copied" class="text-success size-3.75" />
                        <Copy v-else class="text-muted-foreground size-3.75" />
                    </Button>
                </div>
                <p class="text-muted-foreground text-[12.5px]">
                    Envoyez vos requêtes en
                    <span class="font-mono">POST</span>
                    vers cette URL pour déclencher le workflow.
                </p>
            </template>

            <p
                v-else
                class="text-muted-foreground text-[12.5px]"
                data-test="webhook-url-empty"
            >
                Sauvegardez le workflow pour générer l’URL.
            </p>
        </div>

        <Button
            v-if="url"
            variant="outline"
            size="sm"
            class="w-full"
            :disabled="!canUpdateWorkflow || regenerating"
            data-test="webhook-regenerate"
            @click="setDialogOpen(true)"
        >
            <Spinner v-if="regenerating" class="size-3.5" />
            <RefreshCw v-else class="size-3.5" />
            Régénérer le token
        </Button>

        <!-- Hint idempotence (v-pre : nom de header littéral, jamais interpolé) -->
        <Alert class="border-info/30 bg-info-soft text-[12.5px]">
            <Info class="text-info size-4" />
            <AlertDescription>
                Ajoutez un header
                <span class="font-mono" v-pre>X-Request-Id</span>
                : la même valeur ne déclenchera qu’une seule exécution (fenêtre
                de 24 h).
            </AlertDescription>
        </Alert>

        <!-- Confirmation de régénération : l'ancienne URL meurt immédiatement -->
        <Dialog :open="regenerateDialogOpen" @update:open="setDialogOpen">
            <DialogContent
                class="sm:max-w-md"
                data-test="webhook-regenerate-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Régénérer le token</DialogTitle>
                    <DialogDescription>
                        Une nouvelle URL sera générée. L’URL actuelle cessera de
                        fonctionner immédiatement — pensez à mettre à jour les
                        systèmes qui l’appellent.
                    </DialogDescription>
                </DialogHeader>

                <Alert
                    class="border-warning/25 bg-warning-soft"
                    data-test="webhook-regenerate-warning"
                >
                    <TriangleAlert class="text-warning size-4" />
                    <AlertDescription>
                        Cette action est irréversible.
                    </AlertDescription>
                </Alert>

                <DialogFooter class="gap-2">
                    <Button
                        variant="ghost"
                        :disabled="regenerating"
                        data-test="webhook-regenerate-cancel"
                        @click="setDialogOpen(false)"
                    >
                        Annuler
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="regenerating"
                        data-test="webhook-regenerate-confirm"
                        @click="confirmRegenerate"
                    >
                        <Spinner v-if="regenerating" class="size-4" />
                        <RefreshCw v-else class="size-4" />
                        Régénérer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
