import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/*
 * Echo et vue-sonner sont remplacés par des doubles espions : ce que la spec
 * épingle, c'est le contrat du module — depuis la phase 13 (F1), il configure
 * Echo LUI-MÊME (broadcaster reverb). app.ts ne configure plus Echo : ce
 * module est le SEUL point d'entrée Echo de l'app, chargé dynamiquement en
 * DEV seulement (import.meta.env.DEV + garde window dans app.ts). En
 * production, ni ce module ni pusher-js ne sont donc empaquetés.
 */
const configureEcho = vi.fn();
const echoIsConfigured = vi.fn(() => true);
const listen = vi.fn();
const channel = vi.fn(() => ({ listen }));
const echo = vi.fn(() => ({ channel }));
const toastInfo = vi.fn();

vi.mock('@laravel/echo-vue', () => ({
    configureEcho,
    echo,
    echoIsConfigured,
}));

vi.mock('vue-sonner', () => ({
    toast: { info: toastInfo },
}));

const { initializeRealtimeDebug } = await import('@/lib/realtimeDebug');

/** Payload d'un DebugPing (miroir du type local du module). */
function ping(overrides: Partial<{ message: string; sentAt: string }> = {}): {
    message: string;
    sentAt: string;
} {
    return { message: 'Test de diffusion', sentAt: '10:00:00', ...overrides };
}

describe('initializeRealtimeDebug', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        echoIsConfigured.mockReturnValue(true);
        vi.spyOn(console, 'log').mockImplementation(() => {});
        vi.spyOn(console, 'info').mockImplementation(() => {});
        // Déterministe : la valeur du .env local ne doit pas influer sur la
        // spec — chaque test restitue le canal qu'il veut observer.
        vi.stubEnv('VITE_REVERB_APP_CHANNEL', 'faucon-debug');
    });

    afterEach(() => {
        vi.unstubAllEnvs();
        vi.restoreAllMocks();
    });

    it(
        'configure Echo exactement une fois, en broadcaster reverb ' +
            "(contrat : le module est le seul point d'entrée Echo)",
        () => {
            initializeRealtimeDebug();

            expect(configureEcho).toHaveBeenCalledTimes(1);
            expect(configureEcho).toHaveBeenCalledWith({
                broadcaster: 'reverb',
            });
        },
    );

    it("s'abonne au canal VITE_REVERB_APP_CHANNEL de l'application", () => {
        initializeRealtimeDebug();

        expect(channel).toHaveBeenCalledWith('faucon-debug');
        expect(listen).toHaveBeenCalledWith('DebugPing', expect.any(Function));
    });

    it('repli sur le canal « debug » quand VITE_REVERB_APP_CHANNEL est absent', () => {
        vi.stubEnv('VITE_REVERB_APP_CHANNEL', '');

        initializeRealtimeDebug();

        expect(channel).toHaveBeenCalledWith('debug');
    });

    it('route chaque DebugPing entrant vers un toast info horodaté', () => {
        initializeRealtimeDebug();

        const [eventName, handler] = listen.mock.calls[0]!;
        expect(eventName).toBe('DebugPing');

        (handler as (payload: { message: string; sentAt: string }) => void)(
            ping(),
        );

        expect(toastInfo).toHaveBeenCalledTimes(1);
        expect(toastInfo).toHaveBeenCalledWith('Reverb · Test de diffusion', {
            description: expect.stringContaining('(émis à 10:00:00)'),
        });
    });

    it("avertit et ne s'abonne pas si Echo n'est pas configurable", () => {
        echoIsConfigured.mockReturnValue(false);
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

        initializeRealtimeDebug();

        expect(warnSpy).toHaveBeenCalledWith(
            '[reverb-debug] Echo non configuré, écoute annulée.',
        );
        expect(channel).not.toHaveBeenCalled();
    });
});
