<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, CircleX } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/composables/useNotifications';
import {
    notificationDetail,
    notificationTitle,
    readNotificationsSummary,
} from '@/lib/notificationFormat';
import { formatExecutionDate } from '@/lib/executionFormat';
import { index as notificationsIndex } from '@/routes/notifications';

/*
 * Cloche de notifications (phase 10, D9 — miroir du topbar de la maquette
 * dashboard.html) : badge point si non-lues, dropdown des 5 dernières
 * non-lues, clic = mark-read PUIS navigation deep-link vers l'exécution.
 *
 * Données : prop racine `notifications` (aucune requête à l'ouverture).
 * Lecture DÉFENSIVE via readNotificationsSummary — sur la page
 * notifications/Index, la prop de PAGE du même nom (paginator) écrase la
 * racine ; la cloche y affiche alors son état vide sans erreur.
 *
 * Le badge est mis à jour aux navigations complètes seulement (prop racine)
 * — limitation assumée de la phase, pas de polling ni de push.
 */
const page = usePage();

const summary = computed(() =>
    readNotificationsSummary(page.props.notifications),
);

const unreadCount = computed(() => summary.value?.unreadCount ?? 0);

const historyUrl = computed(() => notificationsIndex().url);

/** Mark-read d'abord (la prop racine se recharge avec la réponse), PUIS
 * navigation vers l'exécution visée — dans la BONNE équipe (payload). */
const {
    markAllProcessing,
    markReadAndNavigate: open,
    markAllRead,
} = useNotifications();
</script>

<template>
    <DropdownMenu v-if="summary">
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="group relative h-9 w-9 cursor-pointer"
                :aria-label="
                    unreadCount > 0
                        ? `Notifications — ${unreadCount} non lue${unreadCount > 1 ? 's' : ''}`
                        : 'Notifications'
                "
                data-test="notifications-bell"
            >
                <Bell class="size-5 opacity-80 group-hover:opacity-100" />
                <span
                    v-if="unreadCount > 0"
                    class="bg-brand absolute top-1.5 right-1.5 size-2 rounded-full"
                    data-test="notifications-bell-dot"
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            class="w-[340px] max-w-[calc(100vw-2rem)] p-1.5"
        >
            <!-- En-tête : titre + « Tout marquer lu » (maquette). -->
            <div class="flex items-center justify-between px-2 pt-1 pb-2">
                <p class="text-muted-foreground text-xs font-medium">
                    Notifications
                </p>
                <Button
                    variant="link"
                    class="h-auto p-0 text-xs"
                    :disabled="markAllProcessing || unreadCount === 0"
                    data-test="notifications-mark-all"
                    @click="markAllRead()"
                >
                    Tout marquer lu
                </Button>
            </div>

            <DropdownMenuSeparator />

            <!-- Empty state (D9). -->
            <p
                v-if="summary.recent.length === 0"
                class="text-muted-foreground px-2 py-6 text-center text-sm"
                data-test="notifications-empty"
            >
                Aucune notification
            </p>

            <DropdownMenuItem
                v-for="item in summary.recent"
                :key="item.id"
                class="items-start gap-2.5 px-2 py-2"
                :data-test="`notification-item-${item.id}`"
                @click="open(item)"
            >
                <!-- Règle CVD : icône + libellé, jamais la couleur seule. -->
                <CircleX
                    v-if="item.type === 'execution_failed'"
                    class="text-danger mt-0.5 size-4 flex-none"
                />
                <Bell
                    v-else
                    class="text-muted-foreground mt-0.5 size-4 flex-none"
                />

                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] leading-snug font-medium">
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

                <!-- Dot non-lue (tout le `recent` est non lu côté back). -->
                <span
                    class="bg-brand mt-1.5 size-[7px] flex-none rounded-full"
                    aria-hidden="true"
                />
            </DropdownMenuItem>

            <template v-if="summary.recent.length > 0">
                <DropdownMenuSeparator />
            </template>

            <DropdownMenuItem as-child class="justify-center">
                <Link
                    :href="historyUrl"
                    class="text-[13px] font-medium"
                    data-test="notifications-history-link"
                >
                    Tout voir
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
