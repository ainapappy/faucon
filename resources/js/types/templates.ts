/*
 * Types du domaine Templates (phase 9).
 *
 * Miroir exact de la projection Inertia de
 * `App\Http\Controllers\Templates\WorkflowTemplateController@index` :
 * le snapshot `graph` est la forme payload Phase 3 (camelCase), lu tel quel
 * en base — jamais ré-encodé. Réutilise `WorkflowGraphPayload` de
 * `types/workflows.ts` : aucune redéclaration de la forme du graphe.
 */
import type { WorkflowGraphPayload } from './workflows';

/** Origine d'un template — `system` = seed (team_id null), `team` = publication d'équipe. */
export type TemplateOrigin = 'system' | 'team';

/** Snapshot du graphe d'un template — identique au payload d'écriture du builder. */
export type TemplateGraph = WorkflowGraphPayload;

/** Carte de la galerie (contrôleur `templates.index`, ordre par id — seeds d'abord). */
export type WorkflowTemplateListItem = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    origin: TemplateOrigin;
    nodesCount: number;
    graph: TemplateGraph;
};
