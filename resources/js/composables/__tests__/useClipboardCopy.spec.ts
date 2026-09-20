import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    COPIED_RESET_MS,
    useClipboardCopy,
    writeClipboard,
} from '@/composables/useClipboardCopy';

/*
 * Harnais du repli historique : un document minimal (textarea + execCommand),
 * installé dans l'environnement node — le composable ne doit jamais lever
 * même quand le DOM est absent.
 */
function installLegacyDocument(execCommand: () => boolean): void {
    vi.stubGlobal('document', {
        createElement: () => ({
            value: '',
            style: {} as Record<string, string>,
            setAttribute: vi.fn(),
            select: vi.fn(),
        }),
        body: { appendChild: vi.fn(), removeChild: vi.fn() },
        execCommand,
    });
}

describe('useClipboardCopy', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('passe copied à true après un succès puis le réinitialise après 2 s', async () => {
        const write = vi.fn(async () => true);
        const { copied, copy } = useClipboardCopy({ write });

        expect(copied.value).toBe(false);

        const pending = copy('https://faucon.app/webhooks/x');
        expect(write).toHaveBeenCalledWith('https://faucon.app/webhooks/x');
        await pending;

        expect(copied.value).toBe(true);

        await vi.advanceTimersByTimeAsync(COPIED_RESET_MS - 1);
        expect(copied.value).toBe(true);

        await vi.advanceTimersByTimeAsync(1);
        expect(copied.value).toBe(false);
    });

    it("laisse copied au repos quand l'écriture échoue", async () => {
        const write = vi.fn(async () => false);
        const { copied, copy } = useClipboardCopy({ write });

        await expect(copy('x')).resolves.toBe(false);
        expect(copied.value).toBe(false);

        await vi.advanceTimersByTimeAsync(COPIED_RESET_MS);
        expect(copied.value).toBe(false);
    });

    it('ré-arme le temporisateur à chaque nouvelle copie', async () => {
        const write = vi.fn(async () => true);
        const { copied, copy } = useClipboardCopy({ write });

        await copy('a');
        await vi.advanceTimersByTimeAsync(1500);

        await copy('b');
        // 2 500 ms après la 1re copie mais 1 000 ms après la 2ᵉ : toujours copié.
        await vi.advanceTimersByTimeAsync(1000);
        expect(copied.value).toBe(true);

        await vi.advanceTimersByTimeAsync(1000);
        expect(copied.value).toBe(false);
    });

    it('reset() revient immédiatement au repos et annule le temporisateur', async () => {
        const write = vi.fn(async () => true);
        const { copied, copy, reset } = useClipboardCopy({ write });

        await copy('a');
        expect(copied.value).toBe(true);

        reset();
        expect(copied.value).toBe(false);

        await vi.advanceTimersByTimeAsync(COPIED_RESET_MS);
        expect(copied.value).toBe(false);
    });
});

describe('writeClipboard (couche par défaut)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('utilise navigator.clipboard quand il est disponible', async () => {
        const writeText = vi.fn(async () => undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        await expect(writeClipboard('abc')).resolves.toBe(true);
        expect(writeText).toHaveBeenCalledWith('abc');
    });

    it('replie sur execCommand quand clipboard.writeText rejette', async () => {
        vi.stubGlobal('navigator', {
            clipboard: {
                writeText: vi.fn(async () => {
                    throw new Error('permission refusée');
                }),
            },
        });
        const execCommand = vi.fn(() => true);
        installLegacyDocument(execCommand);

        await expect(writeClipboard('abc')).resolves.toBe(true);
        expect(execCommand).toHaveBeenCalledWith('copy');
    });

    it('replie sur execCommand quand navigator.clipboard est absent', async () => {
        vi.stubGlobal('navigator', {});
        const execCommand = vi.fn(() => true);
        installLegacyDocument(execCommand);

        await expect(writeClipboard('abc')).resolves.toBe(true);
        expect(execCommand).toHaveBeenCalledWith('copy');
    });

    it('retourne false sans lever quand aucune API n’est disponible', async () => {
        vi.stubGlobal('navigator', {});
        // Aucun document : environnement sans DOM — pas de repli possible.

        await expect(writeClipboard('abc')).resolves.toBe(false);
    });
});
