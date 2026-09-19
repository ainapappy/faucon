/*
 * Sauvegarde automatique du graphe du builder (master §20).
 *
 * Machine à états : `idle → saving → saved | error`. Le PUT complet du graphe
 * est debouncé (~800 ms) ; un changement pendant une sauvegarde re-planifie
 * une sauvegarde après la fin de celle en cours. `flush()` garantit la
 * persistance avant une navigation, et un garde `beforeunload` protège les
 * changements non enregistrés contre la fermeture de l'onglet.
 *
 * La couche HTTP est injectée (`putGraph` / `patchWorkflow`) : le composable
 * reste testable hors navigateur et le page-component reste un simple câblage
 * Wayfinder + `useHttp`.
 */
import type { Ref } from 'vue';
import { onScopeDispose, ref } from 'vue';
import type { SaveState, WorkflowDetail, WorkflowGraphPayload } from '@/types';

/** Champs de métadonnées modifiables par PATCH (`workflows.update`). */
export type WorkflowMetadataFields = Partial<
    Pick<WorkflowDetail, 'name' | 'description' | 'status'>
>;

/** Cible minimale du garde beforeunload (fenêtre en prod, doublure en test). */
export type BeforeUnloadTarget = {
    addEventListener: (
        type: 'beforeunload',
        handler: (event: BeforeUnloadEvent) => void,
    ) => void;
    removeEventListener: (
        type: 'beforeunload',
        handler: (event: BeforeUnloadEvent) => void,
    ) => void;
};

/**
 * Échec de requête portant des messages lisibles (422 agrégés, 401, 500…).
 * Construit par le câblage `useHttp` de la page ; le saver se contente de
 * collecter `messages`.
 */
export class RequestFailure extends Error {
    public readonly messages: string[];

    public constructor(messages: string[], message = 'Request failed') {
        super(message);
        this.name = 'RequestFailure';
        this.messages = messages;
    }
}

export type UseWorkflowSaverOptions = {
    /** Payload courant du graphe, capturé au démarrage de chaque sauvegarde. */
    getPayload: () => WorkflowGraphPayload;
    /** Le graphe contient-il des changements non enregistrés ? */
    isDirty: () => boolean;
    /**
     * Appelé après un PUT réussi avec le payload qui vient d'être persisté :
     * la baseline de `isDirty` devient ce snapshot, si bien qu'un changement
     * survenu pendant la sauvegarde reste « dirty ».
     */
    markSynced: (payload: WorkflowGraphPayload) => void;
    /** PUT `workflows.graph.update` — rejette avec `RequestFailure` en cas d'erreur. */
    putGraph: (payload: WorkflowGraphPayload) => Promise<void>;
    /** PATCH `workflows.update` (nom, description, statut) — optionnel. */
    patchWorkflow?: (fields: WorkflowMetadataFields) => Promise<void>;
    /** Notification d'erreur (toast côté page) avec les messages agrégés. */
    onError?: (messages: string[]) => void;
    debounceMs?: number;
    guardTarget?: BeforeUnloadTarget | null;
};

export type UseWorkflowSaverReturn = {
    state: Ref<SaveState>;
    savedAt: Ref<string | null>;
    errors: Ref<string[]>;
    scheduleSave: () => void;
    saveNow: () => Promise<boolean>;
    flush: () => Promise<boolean>;
    saveMetadata: (fields: WorkflowMetadataFields) => Promise<boolean>;
    dispose: () => void;
};

function extractMessages(error: unknown): string[] {
    if (error instanceof RequestFailure && error.messages.length > 0) {
        return error.messages;
    }
    if (error instanceof Error && error.message) {
        return [error.message];
    }
    return ['Une erreur inattendue est survenue lors de l’enregistrement.'];
}

/** Heure locale « HH:MM » affichée à côté de « Enregistré ». */
export function formatSavedTime(date: Date): string {
    return date.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function useWorkflowSaver(
    options: UseWorkflowSaverOptions,
): UseWorkflowSaverReturn {
    const debounceMs = options.debounceMs ?? 800;
    const state = ref<SaveState>('idle');
    const savedAt = ref<string | null>(null);
    const errors = ref<string[]>([]);

    let timer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;
    let pendingAfterSave = false;
    let currentSave: Promise<boolean> | null = null;

    function notifyError(error: unknown): void {
        errors.value = extractMessages(error);
        options.onError?.(errors.value);
    }

    async function performSave(): Promise<boolean> {
        inFlight = true;
        currentSave = performSaveInternal();
        try {
            return await currentSave;
        } finally {
            inFlight = false;
            currentSave = null;
            if (pendingAfterSave) {
                pendingAfterSave = false;
                if (options.isDirty()) {
                    scheduleSave();
                }
            }
        }
    }

    async function performSaveInternal(): Promise<boolean> {
        state.value = 'saving';
        errors.value = [];
        try {
            const snapshot = options.getPayload();
            await options.putGraph(snapshot);
            options.markSynced(snapshot);
            state.value = 'saved';
            savedAt.value = formatSavedTime(new Date());
            return true;
        } catch (error) {
            state.value = 'error';
            notifyError(error);
            return false;
        }
    }

    /** Re-planifie la sauvegarde debouncée ; co-programme une re-planification si un PUT est déjà en vol. */
    function scheduleSave(): void {
        if (inFlight) {
            pendingAfterSave = true;
            return;
        }
        if (timer !== null) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => {
            timer = null;
            void performSave();
        }, debounceMs);
    }

    /** Sauvegarde immédiate (annule le debounce en attente). */
    function saveNow(): Promise<boolean> {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        if (inFlight) {
            pendingAfterSave = true;
            return currentSave ?? Promise.resolve(false);
        }
        return performSave();
    }

    /** À appeler avant toute navigation : persiste les changements en attente. */
    async function flush(): Promise<boolean> {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        if (inFlight) {
            pendingAfterSave = false;
            await currentSave;
            return options.isDirty() ? performSave() : true;
        }
        return options.isDirty() ? performSave() : true;
    }

    async function saveMetadata(
        fields: WorkflowMetadataFields,
    ): Promise<boolean> {
        if (!options.patchWorkflow) {
            return false;
        }
        try {
            await options.patchWorkflow(fields);
            errors.value = [];
            return true;
        } catch (error) {
            notifyError(error);
            return false;
        }
    }

    const guardHandler = (event: BeforeUnloadEvent): void => {
        if (!options.isDirty()) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    };

    const guardTarget =
        options.guardTarget === undefined
            ? typeof window !== 'undefined'
                ? window
                : null
            : options.guardTarget;

    guardTarget?.addEventListener('beforeunload', guardHandler);

    function dispose(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        guardTarget?.removeEventListener('beforeunload', guardHandler);
    }

    onScopeDispose(dispose, true);

    return {
        state,
        savedAt,
        errors,
        scheduleSave,
        saveNow,
        flush,
        saveMetadata,
        dispose,
    };
}
