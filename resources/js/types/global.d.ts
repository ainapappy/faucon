import type { Auth } from '@/types/auth';
import type { NotificationsSummary } from '@/types/notifications';
import type { Team } from '@/types/teams';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            /**
             * Cloche (phase 10, D5) — null si invité. PIÈGE : sur la page
             * `notifications/Index`, la prop de PAGE du même nom (paginator
             * aplati) ÉCRASE cette prop racine — lire la forme via
             * `readNotificationsSummary` (lib/notificationFormat).
             */
            notifications: NotificationsSummary | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
