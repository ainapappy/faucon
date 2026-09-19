/*
 * État et logique dérivée de la liste des workflows (page `workflows/Index`) :
 * recherche client, chips de statut, tri, bascule grille/liste et bascule
 * optimiste Actif/En pause (PATCH silencieux).
 *
 * Le tri « Exécutions » est présent pour rester fidèle à la maquette mais est
 * volontairement sans effet : il n'existe aucun compteur d'exécutions avant
 * les phases 4-7 (amendement A2).
 */
import type { Ref } from 'vue';
import { computed, ref } from 'vue';
import type { WorkflowListItem, WorkflowStatus } from '@/types';

export type WorkflowListFilter = 'all' | 'active' | 'paused';
export type WorkflowListSort = 'recent' | 'name' | 'runs';
export type WorkflowListView = 'grid' | 'list';

/** Résultat de la requête de bascule de statut (construite côté page avec useHttp). */
export type ToggleStatusResult = {
    ok: boolean;
    messages?: string[];
};

export type UseWorkflowListOptions = {
    /** Liste des workflows (copie locale réactive de la prop Inertia). */
    items: Ref<WorkflowListItem[]>;
    /**
     * PATCH `workflows.update` — rejette ou renvoie les messages d'erreur.
     * La mise à jour optimiste et le repli sont gérés ici.
     */
    toggleRequest: (
        workflow: WorkflowListItem,
        status: WorkflowStatus,
    ) => Promise<ToggleStatusResult>;
};

export type UseWorkflowListReturn = {
    query: Ref<string>;
    filter: Ref<WorkflowListFilter>;
    sort: Ref<WorkflowListSort>;
    view: Ref<WorkflowListView>;
    counts: Ref<Record<WorkflowListFilter, number>>;
    filtered: Ref<WorkflowListItem[]>;
    togglingIds: Ref<Set<number>>;
    toggle: (workflow: WorkflowListItem) => Promise<void>;
};

export function useWorkflowList(
    options: UseWorkflowListOptions,
): UseWorkflowListReturn {
    const query = ref('');
    const filter = ref<WorkflowListFilter>('all');
    const sort = ref<WorkflowListSort>('recent');
    const view = ref<WorkflowListView>('grid');
    const togglingIds = ref<Set<number>>(new Set());

    const counts = computed<Record<WorkflowListFilter, number>>(() => {
        const items = options.items.value;
        const active = items.filter((item) => item.status === 'active').length;
        return { all: items.length, active, paused: items.length - active };
    });

    const filtered = computed<WorkflowListItem[]>(() => {
        const needle = query.value.trim().toLowerCase();
        let list = options.items.value.filter((item) => {
            if (filter.value === 'active' && item.status !== 'active') {
                return false;
            }
            if (filter.value === 'paused' && item.status !== 'draft') {
                return false;
            }
            if (!needle) {
                return true;
            }
            return (
                item.name.toLowerCase().includes(needle) ||
                (item.description ?? '').toLowerCase().includes(needle)
            );
        });

        if (sort.value === 'name') {
            list = [...list].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
        }
        // « runs » : no-op tant qu'il n'existe pas de compteur d'exécutions (A2).

        return list;
    });

    async function toggle(workflow: WorkflowListItem): Promise<void> {
        if (togglingIds.value.has(workflow.id)) {
            return;
        }
        const nextStatus: WorkflowStatus =
            workflow.status === 'active' ? 'draft' : 'active';

        // Mise à jour optimiste, repli si le PATCH échoue (422 ex. activation sans trigger).
        const previousStatus = workflow.status;
        workflow.status = nextStatus;
        togglingIds.value = new Set(togglingIds.value).add(workflow.id);

        try {
            const result = await options.toggleRequest(workflow, nextStatus);
            if (!result.ok) {
                workflow.status = previousStatus;
            }
        } catch {
            workflow.status = previousStatus;
        } finally {
            const next = new Set(togglingIds.value);
            next.delete(workflow.id);
            togglingIds.value = next;
        }
    }

    return {
        query,
        filter,
        sort,
        view,
        counts,
        filtered,
        togglingIds,
        toggle,
    };
}
