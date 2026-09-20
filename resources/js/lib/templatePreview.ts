/*
 * Aperçu SVG statique du graphe d'un template (phase 9, D10) — pur, testé Vitest.
 *
 * La maquette `templates.html` rend elle-même l'aperçu en SVG statique
 * (`tplSvg`) : l'implémentation fidèle EST le rendu statique (pas de VueFlow
 * par carte). Ce module normalise les positions réelles du canvas (espacées
 * de ~320 px) vers l'espace compact de la maquette (260 px de large, nodes
 * 70 × 22), calcule les courbes de Bézier des arêtes et résout la couleur
 * catégorielle de chaque node.
 *
 * Règle CVD : la couleur ne porte jamais l'information seule — le libellé
 * (`node.name` tronqué) reste affiché dans l'aperçu.
 */
import { categoryPresentation } from '@/lib/nodeCategories';
import type { NodeTypeCatalog, TemplateGraph } from '@/types';

/** Largeur du viewBox de l'aperçu (valeur maquette). */
export const PREVIEW_VIEW_WIDTH = 260;
/** Largeur d'un node d'aperçu (valeur maquette). */
export const PREVIEW_NODE_WIDTH = 70;
/** Hauteur d'un node d'aperçu (valeur maquette). */
export const PREVIEW_NODE_HEIGHT = 22;
/** Marge gauche/droite de l'aperçu (valeur maquette : premier node à x = 6). */
export const PREVIEW_PADDING = 6;
/** Hauteur minimale de l'aperçu (plancher D10). */
export const PREVIEW_MIN_HEIGHT = 64;
/** Écart vertical entre deux rangées de nodes (proche des aperçus maquette). */
export const PREVIEW_ROW_STEP = 56;
/** Marge verticale au-dessus/en-dessous du graphe. */
export const PREVIEW_VERTICAL_PADDING = 10;
/** Longueur maximale du libellé d'un node (au-delà : tronqué avec une ellipse). */
export const PREVIEW_LABEL_MAX_CHARS = 12;

/** Node d'aperçu positionné dans l'espace compact du viewBox. */
export type PreviewNode = {
    key: string;
    x: number;
    y: number;
    label: string;
    /** Token CSS `--cat-N` résolu par le thème (palette catégorielle validée CVD). */
    colorToken: string;
};

/** Layout complet consommé par `TemplateGraphPreview.vue`. */
export type TemplatePreviewLayout = {
    viewBox: string;
    height: number;
    nodes: PreviewNode[];
    edgePaths: string[];
};

/** Libellé d'aperçu : nom du node tronqué (~12 caractères + ellipse). */
export function previewLabel(name: string): string {
    const trimmed = name.trim();

    if (trimmed.length <= PREVIEW_LABEL_MAX_CHARS) {
        return trimmed;
    }

    return `${trimmed.slice(0, PREVIEW_LABEL_MAX_CHARS).trimEnd()}…`;
}

/** Arrondi à 2 décimales : évite le bruit flottant dans les attributs `d` du SVG. */
function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

/**
 * Courbe de Bézier d'une arête, formule exacte de la maquette :
 * `dx = max(18, |x2 - x1| * 0.45)`, points d'ancrage sur les milieux
 * verticaux (sortie droite du node source, entrée gauche du node cible).
 */
export function previewEdgePath(
    source: PreviewNode,
    target: PreviewNode,
): string {
    const x1 = round2(source.x + PREVIEW_NODE_WIDTH);
    const y1 = round2(source.y + PREVIEW_NODE_HEIGHT / 2);
    const x2 = round2(target.x);
    const y2 = round2(target.y + PREVIEW_NODE_HEIGHT / 2);
    const dx = round2(Math.max(18, Math.abs(x2 - x1) * 0.45));

    return `M ${x1} ${y1} C ${round2(x1 + dx)} ${y1}, ${round2(x2 - dx)} ${y2}, ${x2} ${y2}`;
}

/**
 * Construit le layout d'aperçu d'un graphe de template.
 *
 * - Positions normalisées indépendamment en x et en y (les proportions du
 *   canvas ne sont pas préservées : les nodes gardent la taille maquette) ;
 * - hauteur dérivée de la structure en rangées du graphe (l'aspect vertical),
 *   plancher 64 px — une rangée tient dans le plancher, deux rangées
 *   approchent la hauteur des aperçus de la maquette (96–110 px) ;
 * - couleur de node = présentation catégorielle du catalogue, repli muet sur
 *   la première catégorie pour un type inconnu du catalogue servi en prop ;
 * - arêtes pendantes (clé source ou cible absente) ignorées silencieusement.
 */
export function buildPreviewLayout(
    graph: TemplateGraph,
    nodeTypes: NodeTypeCatalog,
): TemplatePreviewLayout {
    const graphNodes = graph.nodes ?? [];
    const graphEdges = graph.edges ?? [];

    if (graphNodes.length === 0) {
        return {
            viewBox: `0 0 ${PREVIEW_VIEW_WIDTH} ${PREVIEW_MIN_HEIGHT}`,
            height: PREVIEW_MIN_HEIGHT,
            nodes: [],
            edgePaths: [],
        };
    }

    const minX = Math.min(...graphNodes.map((node) => node.positionX));
    const maxX = Math.max(...graphNodes.map((node) => node.positionX));
    const minY = Math.min(...graphNodes.map((node) => node.positionY));
    const maxY = Math.max(...graphNodes.map((node) => node.positionY));

    const spanX = maxX - minX;
    const spanY = maxY - minY;
    const rowCount = new Set(graphNodes.map((node) => node.positionY)).size;
    const height = Math.max(
        PREVIEW_MIN_HEIGHT,
        PREVIEW_NODE_HEIGHT +
            (rowCount - 1) * PREVIEW_ROW_STEP +
            2 * PREVIEW_VERTICAL_PADDING,
    );

    const usableWidth =
        PREVIEW_VIEW_WIDTH - 2 * PREVIEW_PADDING - PREVIEW_NODE_WIDTH;
    const usableHeight =
        height - 2 * PREVIEW_VERTICAL_PADDING - PREVIEW_NODE_HEIGHT;

    const nodes: PreviewNode[] = graphNodes.map((node) => {
        const category = nodeTypes[node.type]?.category;
        // Repli muet (première catégorie) pour un type absent du catalogue.
        const presentation = categoryPresentation(category ?? 'trigger');
        const ratioX = spanX === 0 ? 0 : (node.positionX - minX) / spanX;
        const ratioY = spanY === 0 ? 0 : (node.positionY - minY) / spanY;

        return {
            key: node.key,
            x: Math.round((PREVIEW_PADDING + ratioX * usableWidth) * 100) / 100,
            y:
                spanY === 0
                    ? Math.round(((height - PREVIEW_NODE_HEIGHT) / 2) * 100) /
                      100
                    : Math.round(
                          (PREVIEW_VERTICAL_PADDING + ratioY * usableHeight) *
                              100,
                      ) / 100,
            label: previewLabel(node.name),
            colorToken: presentation.colorToken,
        };
    });

    const nodesByKey = new Map(nodes.map((node) => [node.key, node]));
    const edgePaths = graphEdges
        .map((edge) => {
            const source = nodesByKey.get(edge.sourceNodeKey);
            const target = nodesByKey.get(edge.targetNodeKey);

            return source && target ? previewEdgePath(source, target) : null;
        })
        .filter((path): path is string => path !== null);

    return {
        viewBox: `0 0 ${PREVIEW_VIEW_WIDTH} ${height}`,
        height,
        nodes,
        edgePaths,
    };
}
