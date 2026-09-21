import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { setUrlDefaults } from '@/wayfinder';

/*
 * Wayfinder : les routes préfixées par l'équipe ({current_team}, {team})
 * s'appuient sur URL::defaults() côté serveur (SetTeamUrlDefaults). La valeur
 * étant dynamique, le code généré garde le placeholder « $currentTeam » comme
 * repli ; on fournit ici le vrai slug à l'exécution depuis les props Inertia
 * partagées. La fonction est réévaluée à chaque génération d'URL : un
 * changement d'équipe courante est immédiatement pris en compte.
 */
setUrlDefaults(() => {
    const slug = usePage().props.currentTeam?.slug;

    return slug ? { current_team: slug, team: slug } : {};
});

// Echo ne sert qu'au debug Reverb en développement (lib/realtimeDebug,
// qui configure Echo lui-même). Import dynamique + garde DEV : en
// production, ni ce module ni pusher-js ne sont chargés — et le bloc
// est éliminé statiquement du bundle.
if (import.meta.env.DEV && typeof window !== 'undefined') {
    void import('@/lib/realtimeDebug').then((m) => m.initializeRealtimeDebug());
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
