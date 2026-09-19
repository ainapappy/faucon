import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { configureEcho } from '@laravel/echo-vue';

// Echo ne vit que dans le navigateur : en SSR, pusher-js n'a pas de clé
// et rejetterait une promesse non gérée au warmup du module graph...
if (typeof window !== 'undefined') {
    configureEcho({
        broadcaster: 'reverb',
    });

    // Écouteur de debug Reverb, actif seulement en développement...
    if (import.meta.env.DEV) {
        void import('@/lib/realtimeDebug').then((m) => m.initializeRealtimeDebug());
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Toutes les pages auth sont rendues dans le layout split (maquettes
// login / register, décliné sur forgot/reset/confirm password).
// Le builder de workflow est plein écran (maquette builder.html), sans shell.
const fullscreenPages = new Set(['workflows/Edit']);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case fullscreenPages.has(name):
                return null;
            case name.startsWith('auth/'):
                return AuthSplitLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
