<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, ChevronLeft, ChevronRight, CircleX } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useNotifications } from '@/composables/useNotifications';
import { formatExecutionDate } from '@/lib/executionFormat';
import {
    notificationDetail,
    notificationTitle,
} from '@/lib/notificationFormat';
import { index as notificationsIndex } from '@/routes/notifications';
import type { NotificationItem, PaginatedNotifications } from '@/types';

/*
 * Historique complet des notifications (phase 10, D10) — paginé 15/page,
 * paginator aplati (`notifications.data` / `.total`, PAS `.meta`). Les lues
 * sont grisées ; un item non lu se marque lu PUIS navigue (même parcours que
 * la cloche), un item déjà lu navigue directement.
 *
 * PIÈGE assumé : la prop de PAGE `notifications` écrase ici la prop racine du
 * même nom — le bouton « Tout marquer lu » reste donc VISIBLE en permanence
 * (son effet est idempotent et le rechargement complet qui suit met à jour la
 * cloche) ; choix documenté au rapport de phase.
 */
type Props = {
    notifications: PaginatedNotifications;
};

const props = defineProps<Props>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Notifications', href: notificationsIndex().url },
        ],
    }),
});

const {
    markAllProcessing,
    markReadAndNavigate,
    navigateToTarget,
    markAllRead,
} = useNotifications();

function goToPage(target: number): void {
    router.get(
        notificationsIndex({ query: { page: target } }).url,
        {},
        { preserveScroll: true, only: ['notifications'] },
    );
}

function openItem(item: NotificationItem): void {
    if (!item.read) {
        markReadAndNavigate(item);

        return;
    }

    navigateToTarget(item);
}
</script>

<template>
    <Head title="Notifications" />

    <div class="space-y-6 px-4 py-6 md:px-8" data-test="notifications-page">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Notifications"
                description="Historique des notifications de vos exécutions."
            />
            <Button
                variant="outline"
                :disabled="markAllProcessing"
                data-test="notifications-page-mark-all"
                @click="markAllRead()"
            >
                Tout marquer lu
            </Button>
        </div>

        <!-- Empty state : aucune notification du tout. -->
        <div
            v-if="notifications.data.length === 0"
            class="flex flex-col items-center gap-2.5 rounded-lg border border-dashed px-6 py-14 text-center"
            data-test="notifications-list-empty"
        >
            <span
                class="bg-brand-soft text-brand-ink flex h-11 w-11 items-center justify-center rounded-lg"
            >
                <Bell class="h-5.5 w-5.5" />
            </span>
            <p class="font-semibold">Aucune notification</p>
            <p class="text-muted-foreground max-w-[42ch] text-sm">
                Les échecs d'exécution de vos workflows manuels apparaîtront
                ici.
            </p>
        </div>

        <!-- Historique paginé — lues grisées. -->
        <div
            v-else
            class="divide-y overflow-hidden rounded-lg border"
            data-test="notifications-list"
        >
            <button
                v-for="item in notifications.data"
                :key="item.id"
                type="button"
                class="hover:bg-accent/50 flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors"
                :class="{ 'opacity-70': item.read }"
                :data-test="`notification-row-${item.id}`"
                @click="openItem(item)"
            >
                <span
                    class="mt-0.5 flex size-8 flex-none items-center justify-center rounded-md"
                    :class="
                        item.read
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-danger-soft text-danger'
                    "
                >
                    <CircleX
                        v-if="item.type === 'execution_failed'"
                        class="size-4"
                    />
                    <Bell v-else class="size-4" />
                </span>

                <span class="min-w-0 flex-1">
                    <span
                        class="block truncate text-sm font-medium"
                        :class="{ 'text-muted-foreground': item.read }"
                    >
                        {{ notificationTitle(item) }}
                    </span>
                    <span
                        v-if="notificationDetail(item)"
                        class="text-muted-foreground mt-0.5 line-clamp-2 block text-xs"
                    >
                        {{ notificationDetail(item) }}
                    </span>
                    <small class="text-muted-foreground mt-1 block text-[11px]">
                        {{ formatExecutionDate(item.createdAt) }}
                    </small>
                </span>

                <span
                    v-if="!item.read"
                    class="bg-danger-soft text-danger mt-1 flex-none rounded-full px-2 py-0.5 text-[10px] font-semibold"
                >
                    Non lue
                </span>
            </button>
        </div>

        <!-- Pagination (pattern executions/Index). -->
        <div
            v-if="notifications.last_page > 1"
            class="flex items-center justify-between"
        >
            <p class="text-muted-foreground text-sm">
                Page {{ notifications.current_page }} sur
                {{ notifications.last_page }} —
                {{ notifications.total }} notifications
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="notifications.current_page <= 1"
                    @click="goToPage(notifications.current_page - 1)"
                >
                    <ChevronLeft class="size-4" />
                    Précédente
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        notifications.current_page >= notifications.last_page
                    "
                    @click="goToPage(notifications.current_page + 1)"
                >
                    Suivante
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
