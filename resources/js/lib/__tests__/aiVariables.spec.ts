import { describe, expect, it } from 'vitest';
import {
    aiModeHint,
    aiOutputKeysByType,
    upstreamVariablePaths,
} from '@/lib/aiVariables';
import type { WorkflowEdgeData, WorkflowNodeData } from '@/types';

/*
 * Graphe de référence : t1 (trigger.manual) → a1 (ai.classification) →
 * c1 (logic.condition) → g1 (ai.generation) ← i1 (data.input « lead »).
 */
function demoGraph(): { nodes: WorkflowNodeData[]; edges: WorkflowEdgeData[] } {
    return {
        nodes: [
            {
                key: 't1',
                type: 'trigger.manual',
                name: 'Départ',
                config: {},
                positionX: 0,
                positionY: 0,
            },
            {
                key: 'a1',
                type: 'ai.classification',
                name: 'Classifier',
                config: { model: 'fake/demo' },
                positionX: 200,
                positionY: 0,
            },
            {
                key: 'c1',
                type: 'logic.condition',
                name: 'Condition',
                config: {},
                positionX: 400,
                positionY: 0,
            },
            {
                key: 'i1',
                type: 'data.input',
                name: 'Entrée',
                config: { name: 'lead' },
                positionX: 400,
                positionY: 200,
            },
            {
                key: 'g1',
                type: 'ai.generation',
                name: 'Génération',
                config: { model: 'fake/demo' },
                positionX: 600,
                positionY: 0,
            },
        ],
        edges: [
            {
                id: 1,
                sourceNodeKey: 't1',
                targetNodeKey: 'a1',
                sourceHandle: 'out',
            },
            {
                id: 2,
                sourceNodeKey: 'a1',
                targetNodeKey: 'c1',
                sourceHandle: 'out',
            },
            {
                id: 3,
                sourceNodeKey: 'c1',
                targetNodeKey: 'g1',
                sourceHandle: 'true',
            },
            {
                id: 4,
                sourceNodeKey: 'i1',
                targetNodeKey: 'g1',
                sourceHandle: 'out',
            },
        ],
    };
}

describe('aiOutputKeysByType', () => {
    it('couvre les cinq modes IA avec leur clé de sortie + usage', () => {
        expect(aiOutputKeysByType['ai.prompt']).toEqual(['text', 'usage']);
        expect(aiOutputKeysByType['ai.classification']).toEqual([
            'label',
            'usage',
        ]);
        expect(aiOutputKeysByType['ai.extraction']).toEqual([
            'structured',
            'usage',
        ]);
        expect(aiOutputKeysByType['ai.summarization']).toEqual([
            'text',
            'usage',
        ]);
        expect(aiOutputKeysByType['ai.generation']).toEqual(['text', 'usage']);
    });
});

describe('upstreamVariablePaths', () => {
    it('propose le contexte trigger pour un node sans ancêtre connu', () => {
        const graph = demoGraph();
        expect(upstreamVariablePaths('t1', graph.nodes, graph.edges)).toEqual([
            '{{ trigger.… }}',
        ]);
    });

    it('liste les ancêtres AI avec leurs clés de sortie réelles (BFS)', () => {
        const graph = demoGraph();
        expect(upstreamVariablePaths('c1', graph.nodes, graph.edges)).toEqual([
            '{{ trigger.… }}',
            '{{ a1.label }}',
            '{{ a1.usage }}',
        ]);
    });

    it('cumule ancêtres directs, données d’entrée exposées et IA amont', () => {
        const graph = demoGraph();
        expect(upstreamVariablePaths('g1', graph.nodes, graph.edges)).toEqual([
            '{{ trigger.… }}',
            '{{ c1.… }}',
            '{{ lead.… }}',
            '{{ a1.label }}',
            '{{ a1.usage }}',
        ]);
    });

    it('utilise le nom de variable par défaut d’un data.input sans nom', () => {
        const graph = demoGraph();
        const input = graph.nodes.find((node) => node.key === 'i1');
        if (input) {
            input.config = {};
        }
        expect(upstreamVariablePaths('g1', graph.nodes, graph.edges)).toContain(
            '{{ payload.… }}',
        );
    });

    it('ne duplique pas un ancêtre atteint par plusieurs chemins (diamant)', () => {
        const graph = demoGraph();
        const paths = upstreamVariablePaths('g1', graph.nodes, graph.edges);
        expect(paths.filter((path) => path === '{{ a1.label }}')).toHaveLength(
            1,
        );
    });
});

describe('aiModeHint', () => {
    it('donne un hint aux modes classification et extraction', () => {
        expect(aiModeHint('ai.classification')).toContain('virgules');
        expect(aiModeHint('ai.extraction')).toContain('clé: type');
        expect(aiModeHint('ai.extraction')).toContain('text, number, boolean');
    });

    it('ne donne pas de hint aux autres modes ni hors IA', () => {
        expect(aiModeHint('ai.prompt')).toBeNull();
        expect(aiModeHint('ai.summarization')).toBeNull();
        expect(aiModeHint('ai.generation')).toBeNull();
        expect(aiModeHint('action.http')).toBeNull();
    });
});
