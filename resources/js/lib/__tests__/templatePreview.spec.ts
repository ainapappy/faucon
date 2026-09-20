import { describe, expect, it } from 'vitest';
import {
    buildPreviewLayout,
    previewEdgePath,
    previewLabel,
    PREVIEW_LABEL_MAX_CHARS,
    PREVIEW_MIN_HEIGHT,
    type PreviewNode,
} from '@/lib/templatePreview';
import type {
    NodeCategory,
    NodeTypeCatalog,
    NodeTypeDefinition,
    WorkflowGraphPayload,
} from '@/types';

/** Définition de catalogue minimale (seuls type et category sont lus pour l'aperçu). */
function definition(type: string, category: NodeCategory): NodeTypeDefinition {
    return {
        type,
        category,
        label: type,
        description: '',
        icon: 'zap',
        input: false,
        outputs: [],
        fields: [],
    };
}

const catalog: NodeTypeCatalog = {
    'trigger.webhook': definition('trigger.webhook', 'trigger'),
    'data.input': definition('data.input', 'data'),
    'logic.condition': definition('logic.condition', 'logic'),
    'ai.classification': definition('ai.classification', 'ai'),
    'action.email': definition('action.email', 'action'),
};

/** Node de graphe persisté (positions entières du canvas, payload Phase 3). */
function node(
    key: string,
    type: string,
    name: string,
    positionX: number,
    positionY: number,
): WorkflowGraphPayload['nodes'][number] {
    return { key, type, name, config: {}, positionX, positionY };
}

function edge(
    sourceNodeKey: string,
    targetNodeKey: string,
): WorkflowGraphPayload['edges'][number] {
    return { sourceNodeKey, targetNodeKey, sourceHandle: 'out' };
}

function graph(
    nodes: WorkflowGraphPayload['nodes'],
    edges: WorkflowGraphPayload['edges'],
): WorkflowGraphPayload {
    return { nodes, edges };
}

describe('buildPreviewLayout', () => {
    it('normalizes positions to the compact mockup space (min left, max right, single row centered)', () => {
        const layout = buildPreviewLayout(
            graph(
                [
                    node('a', 'trigger.webhook', 'Webhook', 100, 200),
                    node('b', 'logic.condition', 'Condition', 420, 200),
                    node('c', 'action.email', 'Email', 740, 200),
                ],
                [],
            ),
            catalog,
        );

        expect(layout.viewBox).toBe('0 0 260 64');
        expect(layout.height).toBe(PREVIEW_MIN_HEIGHT);
        // Largeur utile 178 px (260 − 2 × 6 − 70) : premier node à 6, dernier à 184.
        expect(layout.nodes.map((n) => n.x)).toEqual([6, 95, 184]);
        // Une seule rangée : centrée verticalement dans le plancher de 64 px.
        expect(layout.nodes.map((n) => n.y)).toEqual([21, 21, 21]);
    });

    it('floors the height at 64 for one row and grows with the row structure', () => {
        const singleRow = buildPreviewLayout(
            graph(
                [
                    node('a', 'trigger.webhook', 'Webhook', 100, 200),
                    node('b', 'action.email', 'Email', 420, 200),
                ],
                [],
            ),
            catalog,
        );
        expect(singleRow.height).toBe(PREVIEW_MIN_HEIGHT);

        const twoRows = buildPreviewLayout(
            graph(
                [
                    node('a', 'trigger.webhook', 'Webhook', 100, 100),
                    node('b', 'ai.classification', 'IA', 420, 100),
                    node('c', 'action.email', 'Email', 740, 300),
                ],
                [],
            ),
            catalog,
        );
        // 22 + 56 + 2 × 10 : deux rangées approchent la hauteur maquette (96–110).
        expect(twoRows.height).toBe(98);
        // Rangées mappées sur la hauteur utile : 10 (haut) et 66 (10 + 56).
        expect(twoRows.nodes.map((n) => n.y)).toEqual([10, 10, 66]);
    });

    it('produces one Bézier path per connected edge, using the mockup formula', () => {
        const layout = buildPreviewLayout(
            graph(
                [
                    node('a', 'trigger.webhook', 'Webhook', 100, 200),
                    node('b', 'logic.condition', 'Condition', 420, 200),
                    node('c', 'action.email', 'Email', 740, 200),
                ],
                [edge('a', 'b'), edge('a', 'c')],
            ),
            catalog,
        );

        expect(layout.edgePaths).toHaveLength(2);

        const [a, b, c] = layout.nodes;
        // Arête courte (19 px) : dx planché à 18.
        expect(layout.edgePaths[0]).toBe(
            previewEdgePath(a as PreviewNode, b as PreviewNode),
        );
        expect(layout.edgePaths[0]).toBe('M 76 32 C 94 32, 77 32, 95 32');
        // Arête longue (108 px) : dx = |x2 - x1| * 0.45 (au-delà du plancher).
        expect(layout.edgePaths[1]).toBe(
            previewEdgePath(a as PreviewNode, c as PreviewNode),
        );
        expect(layout.edgePaths[1]).toBe(
            'M 76 32 C 124.6 32, 135.4 32, 184 32',
        );
    });

    it('silently skips dangling edges (unknown source or target key)', () => {
        const layout = buildPreviewLayout(
            graph(
                [node('a', 'trigger.webhook', 'Webhook', 100, 200)],
                [edge('a', 'ghost'), edge('phantom', 'a')],
            ),
            catalog,
        );

        expect(layout.edgePaths).toEqual([]);
    });

    it('resolves node colors from the catalog categories (CVD palette order)', () => {
        const layout = buildPreviewLayout(
            graph(
                [
                    node('a', 'trigger.webhook', 'Webhook', 100, 200),
                    node('b', 'ai.classification', 'IA', 420, 200),
                    node('c', 'action.email', 'Email', 740, 200),
                ],
                [],
            ),
            catalog,
        );

        expect(layout.nodes.map((n) => n.colorToken)).toEqual([
            'var(--cat-1)',
            'var(--cat-3)',
            'var(--cat-4)',
        ]);
    });

    it('falls back silently to the first category color for a type missing from the catalog', () => {
        const layout = buildPreviewLayout(
            graph([node('a', 'mystery.node', 'Mystère', 100, 200)], []),
            catalog,
        );

        expect(layout.nodes[0]?.colorToken).toBe('var(--cat-1)');
    });

    it('returns an empty layout for a graph without nodes', () => {
        const layout = buildPreviewLayout(graph([], []), catalog);

        expect(layout).toEqual({
            viewBox: '0 0 260 64',
            height: PREVIEW_MIN_HEIGHT,
            nodes: [],
            edgePaths: [],
        });
    });
});

describe('previewLabel', () => {
    it('keeps short names as-is', () => {
        expect(previewLabel('Webhook')).toBe('Webhook');
        expect(previewLabel('Traitement d')).toBe('Traitement d');
    });

    it('truncates long names to the max length plus an ellipsis', () => {
        expect(previewLabel('Condition urgente')).toBe('Condition ur…');
        expect(previewLabel('Classification des leads')).toBe(
            `${'Classification des leads'.slice(0, PREVIEW_LABEL_MAX_CHARS)}…`,
        );
    });

    it('trims whitespace before the ellipsis', () => {
        expect(previewLabel('Notification Push')).toBe('Notification…');
        expect(previewLabel('Envoi  du  rapport')).toBe('Envoi  du  r…');
    });
});

describe('previewEdgePath', () => {
    it('anchors on the source right edge and target left edge midpoints', () => {
        const source: PreviewNode = {
            key: 'a',
            x: 6,
            y: 10,
            label: 'A',
            colorToken: 'var(--cat-1)',
        };
        const target: PreviewNode = {
            key: 'b',
            x: 190,
            y: 66,
            label: 'B',
            colorToken: 'var(--cat-4)',
        };

        expect(previewEdgePath(source, target)).toBe(
            'M 76 21 C 127.3 21, 138.7 77, 190 77',
        );
    });
});
