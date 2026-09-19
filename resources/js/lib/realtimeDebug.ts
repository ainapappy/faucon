/**
 * Debug Reverb en développement : s'abonne au canal public de l'application
 * et affiche chaque DebugPing (console + toast). Importé seulement en DEV
 * depuis app.ts, aucun impact en production.
 */
import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { toast } from 'vue-sonner';

type DebugPingPayload = {
    message: string;
    sentAt: string;
};

const debugChannel = (): string =>
    import.meta.env.VITE_PUSHER_APP_CHANNEL || 'debug';

export function initializeRealtimeDebug(): void {
    if (!echoIsConfigured()) {
        console.warn('[reverb-debug] Echo non configuré, écoute annulée.');

        return;
    }

    echo()
        .channel(debugChannel())
        .listen('DebugPing', (payload: DebugPingPayload) => {
            const receivedAt = new Date().toLocaleTimeString();

            console.log('[reverb-debug] DebugPing reçu', payload);
            toast.info(`Reverb · ${payload.message}`, {
                description: `Reçu à ${receivedAt} (émis à ${payload.sentAt})`,
            });
        });

    console.info(
        `[reverb-debug] En écoute sur « ${debugChannel()} » (événement DebugPing).`,
    );
}
