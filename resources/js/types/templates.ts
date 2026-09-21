/*
 * Types du domaine Templates (phase 9).
 *
 * Miroir de la projection Inertia de
 * `App\Http\Controllers\Templates\WorkflowTemplateController@index` :
 * le snapshot complet (`TemplateGraph`) reste la forme payload Phase 3
 * (camelCase) du builder, tandis que la galerie (`templates.index`, phase 13)
 * ne sert plus qu'un graphe aminci (`TemplatePreviewGraph`) — configs de
 * nodes et handles d'arêtes ne quittent jamais le serveur.
 */
import type { WorkflowGraphPayload } from './workflows';

/** Origine d'un template — `system` = seed (team_id null), `team` = publication d'équipe. */
export type TemplateOrigin = 'system' | 'team';

/** Snapshot du graphe d'un template — identique au payload d'écriture du builder. */
export type TemplateGraph = WorkflowGraphPayload;

/**
 * Graphe d'aperçu servi par `templates.index` : le sous-ensemble consommé
 * par `buildPreviewLayout`. Un `TemplateGraph` complet reste assignable
 * (assignabilité structurelle) — le builder n'est pas affecté.
 */
export type TemplatePreviewGraph = {
    nodes: {
        key: string;
        type: string;
        name: string;
        positionX: number;
        positionY: number;
    }[];
    edges: { sourceNodeKey: string; targetNodeKey: string }[];
};

/** Carte de la galerie (contrôleur `templates.index`, ordre par id — seeds d'abord). */
export type WorkflowTemplateListItem = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    origin: TemplateOrigin;
    nodesCount: number;
    graph: TemplatePreviewGraph;
};
