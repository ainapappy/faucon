<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { KeyRound, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DeleteIntegrationDialog from '@/components/integrations/DeleteIntegrationDialog.vue';
import IntegrationCard from '@/components/integrations/IntegrationCard.vue';
import IntegrationFormDialog from '@/components/integrations/IntegrationFormDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useIntegrationConnectionTest } from '@/composables/useIntegrationConnectionTest';
import {
    index as integrationsIndex,
    test as testIntegrationRoute,
} from '@/routes/integrations';
import type { IntegrationSummary, TeamPermissions } from '@/types';

type Props = {
    integrations: IntegrationSummary[];
    permissions: TeamPermissions;
};

const props = defineProps<Props>();

const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

defineOptions({
    layout: (pageProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Intégrations',
                href: integrationsIndex({
                    current_team: pageProps.currentTeam?.slug ?? '',
                }).url,
            },
        ],
    }),
});

const formDialogOpen = ref(false);
const editingIntegration = ref<IntegrationSummary | null>(null);
const deleteDialogOpen = ref(false);
const deletingIntegration = ref<IntegrationSummary | null>(null);

function openCreate(): void {
    editingIntegration.value = null;
    formDialogOpen.value = true;
}

function openEdit(integration: IntegrationSummary): void {
    editingIntegration.value = integration;
    formDialogOpen.value = true;
}

function openDelete(integration: IntegrationSummary): void {
    deletingIntegration.value = integration;
    deleteDialogOpen.value = true;
}

/*
 * Test de connexion : machine idle → testing → résultat du composable ;
 * toasts et rafraîchissement du badge (reload partiel) côté page. La réponse
 * `{ok, message}` est TOUJOURS un résultat — même `ok: false` est un toast
 * d'échec métier, pas une erreur transport.
 */
const http = useHttp();

const { testingId, testConnection } = useIntegrationConnectionTest({
    postTest: (integration) =>
        new Promise((resolve, reject) => {
            http.post(
                testIntegrationRoute({
                    current_team: teamSlug.value,
                    integration: integration.id,
                }).url,
                {
                    onSuccess: (response) => {
                        resolve(response as { ok: boolean; message: string });
                    },
                    onHttpException: () => {
                        reject(new Error('http exception'));
                    },
                },
            ).catch(() => reject(new Error('network')));
        }),
    onResult: (_integration, result) => {
        if (result.ok) {
            toast.success('Connexion réussie', {
                description: result.message,
            });
        } else {
            toast.error('Test de connexion échoué', {
                description: result.message,
            });
        }

        // last_tested_at / last_test_succeeded sont persistés côté serveur.
        router.reload({ only: ['integrations'] });
    },
    onError: () => {
        toast.error('Test impossible', {
            description: 'Erreur réseau — réessayez.',
        });
    },
});
</script>

<template>
    <Head title="Intégrations" />

    <h1 class="sr-only">Intégrations</h1>

    <div class="flex flex-col gap-5">
        <Heading
            title="Intégrations"
            description="Credentials chiffrés au repos — jamais exposés au frontend ni dans les logs."
        />

        <div
            v-if="props.integrations.length > 0"
            class="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-3.5"
            data-test="integrations-grid"
        >
            <IntegrationCard
                v-for="integration in props.integrations"
                :key="integration.id"
                :integration="integration"
                :testing="testingId === integration.id"
                :can-update="props.permissions.canUpdateIntegration"
                :can-delete="props.permissions.canDeleteIntegration"
                @edit="openEdit"
                @remove="openDelete"
                @test="testConnection"
            />

            <button
                v-if="props.permissions.canCreateIntegration"
                class="text-muted-foreground hover:border-ring/50 hover:text-foreground flex min-h-[150px] cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-transparent p-4 transition-colors"
                data-test="integrations-create"
                @click="openCreate"
            >
                <Plus class="size-4.5" />
                Ajouter une intégration
            </button>
        </div>

        <div
            v-else
            class="flex flex-col items-center gap-2.5 rounded-lg border border-dashed px-6 py-14 text-center"
            data-test="integrations-empty"
        >
            <span
                class="bg-brand-soft text-brand-ink flex h-11 w-11 items-center justify-center rounded-lg"
            >
                <KeyRound class="size-5.5" />
            </span>
            <p class="font-semibold">Aucune intégration</p>
            <p class="text-muted-foreground max-w-[42ch] text-sm">
                Connectez vos API et serveurs SMTP pour les utiliser dans vos
                workflows — les credentials sont chiffrés au repos.
            </p>
            <Button
                v-if="props.permissions.canCreateIntegration"
                size="sm"
                class="mt-1.5"
                data-test="integrations-empty-create"
                @click="openCreate"
            >
                <Plus />
                Ajouter une intégration
            </Button>
        </div>
    </div>

    <IntegrationFormDialog
        :open="formDialogOpen"
        :integration="editingIntegration"
        :team-slug="teamSlug"
        @update:open="formDialogOpen = $event"
    />

    <DeleteIntegrationDialog
        :open="deleteDialogOpen"
        :integration="deletingIntegration"
        :team-slug="teamSlug"
        @update:open="deleteDialogOpen = $event"
    />
</template>
