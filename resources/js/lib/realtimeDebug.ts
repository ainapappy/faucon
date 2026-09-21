/**
 * Debug Reverb en développement : configure Echo (broadcaster reverb),
 * s'abonne au canal public de l'application et affiche chaque DebugPing
 * (console + toast).
 *
 * Depuis la phase 13 (F1), ce module est le SEUL point d'entrée Echo de
 * l'app : app.ts ne configure plus Echo et n'importe ce module que
 * dynamiquement sous import.meta.env.DEV (garde window). En production, ni
 * ce module ni pusher-js ne sont chargés. Tout futur usage d'Echo (useEcho
 * et Cie) devra passer après cette initialisation — et réintroduire un
 * import statique assumé dans le bundle de production.
 */
import { configureEcho, echo, echoIsConfigured } from '@laravel/echo-vue';
import { toast } from 'vue-sonner';

type DebugPingPayload = {
    message: string;
    sentAt: string;
};

const debugChannel = (): string =>
    import.meta.env.VITE_REVERB_APP_CHANNEL || 'debug';

export function initializeRealtimeDebug(): void {
    configureEcho({ broadcaster: 'reverb' });

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
