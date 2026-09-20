/*
 * Copie au presse-papiers avec repli (phase 5, lot G — URL webhook).
 *
 * `navigator.clipboard` n'existe qu'en contexte sécurisé (https ou localhost)
 * ; ailleurs — ou si la permission est refusée — repli historique via un
 * textarea temporaire et `document.execCommand('copy')`. L'état `copied`
 * permet au bouton « Copier » d'afficher « Copié » pendant 2 s (maquette
 * builder) avant de revenir au repos.
 *
 * La couche d'écriture bas niveau est injectable : le composable reste
 * testable hors navigateur (Vitest, environnement node), comme les
 * composables HTTP du projet.
 */
import { getCurrentInstance, onBeforeUnmount, ref } from 'vue';
import type { Ref } from 'vue';

/** Durée d'affichage de l'état « Copié » (maquette builder : 2 s). */
export const COPIED_RESET_MS = 2000;

/**
 * Repli historique : textarea hors écran + execCommand. Retourne false si
 * l'API n'existe pas (l'appel peut throw selon les navigateurs — attrapé).
 */
function legacyCopy(text: string): boolean {
    if (
        typeof document === 'undefined' ||
        typeof document.execCommand !== 'function'
    ) {
        return false;
    }
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    let succeeded = false;
    try {
        succeeded = document.execCommand('copy');
    } catch {
        succeeded = false;
    }
    document.body.removeChild(textarea);
    return succeeded;
}

/**
 * Écrit le texte dans le presse-papiers — succès TOUTES voies confondues
 * (API asynchrone, puis repli exécution si elle est absente ou refusée).
 */
export async function writeClipboard(text: string): Promise<boolean> {
    const clipboard =
        typeof navigator !== 'undefined' ? navigator.clipboard : undefined;
    if (clipboard && typeof clipboard.writeText === 'function') {
        try {
            await clipboard.writeText(text);
            return true;
        } catch {
            // Permission refusée / écriture impossible : on tente le repli.
        }
    }
    return legacyCopy(text);
}

export type UseClipboardCopyOptions = {
    /** Écriture bas niveau injectable (tests) — défaut : `writeClipboard`. */
    write?: (text: string) => Promise<boolean>;
    /** Durée de l'état « Copié » (injectable pour les tests). */
    resetMs?: number;
};

export type UseClipboardCopyReturn = {
    /** Vrai pendant `resetMs` après une copie réussie. */
    copied: Ref<boolean>;
    /** Copie le texte ; retourne le succès. Un échec laisse `copied` au repos. */
    copy: (text: string) => Promise<boolean>;
    /** Retour immédiat au repos (annule le temporisateur en cours). */
    reset: () => void;
};

export function useClipboardCopy(
    options: UseClipboardCopyOptions = {},
): UseClipboardCopyReturn {
    const write = options.write ?? writeClipboard;
    const resetMs = options.resetMs ?? COPIED_RESET_MS;

    const copied = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    function clearTimer(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    async function copy(text: string): Promise<boolean> {
        const succeeded = await write(text);
        if (succeeded) {
            copied.value = true;
            clearTimer();
            timer = setTimeout(() => {
                copied.value = false;
                timer = null;
            }, resetMs);
        }
        return succeeded;
    }

    function reset(): void {
        clearTimer();
        copied.value = false;
    }

    // Nettoyage du temporisateur en montage réel ; no-op dans les tests.
    if (getCurrentInstance()) {
        onBeforeUnmount(clearTimer);
    }

    return { copied, copy, reset };
}
