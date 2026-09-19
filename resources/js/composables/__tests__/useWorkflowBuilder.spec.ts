import { describe, expect, it } from 'vitest';
import {
    defaultConfigValue,
    newNodeKey,
    useWorkflowBuilder,
} from '@/composables/useWorkflowBuilder';
import type { NodeTypeCatalog, WorkflowGraph } from '@/types';

/* Catalogue de test — miroir réduit du catalogue backend (`NodeCatalog`). */
const catalog: NodeTypeCatalog = {
    'trigger.manual': {
        type: 'trigger.manual',
        category: 'trigger',
        label: 'Manuel',
        description: 'Lancement depuis l’interface',
        icon: 'play',
        input: false,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [],
    },
    'logic.condition': {
        type: 'logic.condition',
        category: 'logic',
        label: 'Condition',
        description: 'Deux branches : true / false',
        icon: 'git-branch',
        input: true,
        outputs: [
            { id: 'true', label: 'true', position: 0.36 },
            { id: 'false', label: 'false', position: 0.68 },
        ],
        fields: [
            {
                key: 'expression',
                label: 'Expression',
                type: 'text',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: true,
            },
        ],
    },
    'action.email': {
        type: 'action.email',
        category: 'action',
        label: 'Email',
        description: 'Envoie un e-mail transactionnel',
        icon: 'mail',
        input: true,
        outputs: [],
        fields: [
            {
                key: 'to',
                label: 'Destinataire',
                type: 'text',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: true,
            },
            {
                key: 'body',
                label: 'Corps',
                type: 'textarea',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
        ],
    },
    'ai.generation': {
        type: 'ai.generation',
        category: 'ai',
        label: 'Génération',
        description: 'Produit un texte à partir du contexte',
        icon: 'sparkles',
        input: true,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [
            {
                key: 'temperature',
                label: 'Température',
                type: 'range',
                required: false,
                placeholder: null,
                options: null,
                min: 0,
                max: 1,
                step: 0.1,
                mono: false,
            },
        ],
    },
    'data.http_request': {
        type: 'data.http_request',
        category: 'data',
        label: 'Requête HTTP',
        description: 'Appel sortant protégé (garde-fous SSRF)',
        icon: 'globe',
        input: true,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [
            {
                key: 'method',
                label: 'Méthode',
                type: 'select',
                required: false,
                placeholder: null,
                options: ['GET', 'POST', 'PUT'],
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            {
                key: 'url',
                label: 'URL',
                type: 'text',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: true,
            },
        ],
    },
};

function buildGraph(): WorkflowGraph {
    return {
        nodes: [
            {
                key: 'n1',
                type: 'trigger.manual',
                name: 'Départ',
                config: {},
                positionX: 60,
                positionY: 200,
            },
            {
                key: 'n2',
                type: 'action.email',
                name: 'Envoi',
                config: { to: 'a@b.c', body: 'Bonjour' },
                positionX: 300,
                positionY: 200,
            },
        ],
        edges: [
            {
                id: 11,
                sourceNodeKey: 'n1',
                targetNodeKey: 'n2',
                sourceHandle: 'out',
            },
        ],
    };
}

describe('newNodeKey', () => {
    it('génère des clés uniques', () => {
        const keys = new Set(Array.from({ length: 50 }, () => newNodeKey()));
        expect(keys.size).toBe(50);
    });
});

describe('defaultConfigValue', () => {
    it('initialise un select sur sa première option', () => {
        const field = catalog['data.http_request'].fields[0];
        expect(defaultConfigValue(field)).toBe('GET');
    });

    it('initialise un range au milieu du segment', () => {
        const field = catalog['ai.generation'].fields[0];
        expect(defaultConfigValue(field)).toBe(0.5);
    });

    it('initialise un texte à une chaîne vide', () => {
        const field = catalog['action.email'].fields[0];
        expect(defaultConfigValue(field)).toBe('');
    });
});

describe('useWorkflowBuilder', () => {
    it("conserve le graphe lors d'un aller-retour vers le payload", () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());
        expect(builder.isDirty.value).toBe(false);

        const payload = builder.toGraphPayload();
        expect(payload.nodes).toHaveLength(2);
        expect(payload.nodes[0]).toEqual({
            key: 'n1',
            type: 'trigger.manual',
            name: 'Départ',
            config: {},
            positionX: 60,
            positionY: 200,
        });
        expect(payload.edges).toEqual([
            { sourceNodeKey: 'n1', targetNodeKey: 'n2', sourceHandle: 'out' },
        ]);
    });

    it('marque le graphe dirty après mutation puis resynchronise', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());
        builder.setNodeName('n1', 'Renommé');
        expect(builder.isDirty.value).toBe(true);

        builder.markSynced();
        expect(builder.isDirty.value).toBe(false);
    });

    it('ajoute un node avec config initialisée du schéma et position centrée/arrondie', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());

        const node = builder.addNode('ai.generation', { x: 421.6, y: 300 });
        expect(node).not.toBeNull();
        expect(node?.type).toBe('ai.generation');
        expect(node?.name).toBe('Génération');
        expect(node?.config.temperature).toBe(0.5);
        expect(node?.positionX).toBe(Math.round(421.6 - 190 / 2));

        // Un type inconnu du catalogue est refusé silencieusement.
        expect(builder.addNode('ghost.unknown', { x: 0, y: 0 })).toBeNull();
    });

    it('refuse les connexions en boucle sur soi-même et les doublons', () => {
        const graph: WorkflowGraph = {
            nodes: [
                {
                    key: 'a',
                    type: 'logic.condition',
                    name: 'Condition',
                    config: {},
                    positionX: 0,
                    positionY: 0,
                },
                {
                    key: 'b',
                    type: 'action.email',
                    name: 'Email',
                    config: {},
                    positionX: 300,
                    positionY: 0,
                },
            ],
            edges: [],
        };
        const builder = useWorkflowBuilder(catalog, graph);

        // Self-loop refusée.
        expect(
            builder.connect({ source: 'a', target: 'a', sourceHandle: 'true' }),
        ).toBe(false);

        expect(
            builder.connect({ source: 'a', target: 'b', sourceHandle: 'true' }),
        ).toBe(true);

        // Doublon (même source, cible et handle) refusé.
        expect(
            builder.connect({ source: 'a', target: 'b', sourceHandle: 'true' }),
        ).toBe(false);

        // Le même couple via l'autre branche reste permis.
        expect(
            builder.connect({
                source: 'a',
                target: 'b',
                sourceHandle: 'false',
            }),
        ).toBe(true);

        // Handle inexistant pour le type source refusé.
        expect(
            builder.connect({ source: 'a', target: 'b', sourceHandle: 'out' }),
        ).toBe(false);

        expect(builder.edges.value).toHaveLength(2);
    });

    it('supprime un node et ses arêtes en cascade', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());
        builder.removeNode('n1');

        expect(builder.nodes.value.map((node) => node.key)).toEqual(['n2']);
        expect(builder.edges.value).toEqual([]);
    });

    it('supprime une arête isolée', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());
        builder.selectEdge('11');
        builder.removeEdge('11');

        expect(builder.edges.value).toEqual([]);
        expect(builder.selection.value).toBeNull();
    });

    it("arrondit les positions à l'entier lors d'un déplacement", () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());
        builder.moveNode('n1', 123.6, 87.2);

        const node = builder.nodes.value[0];
        expect(node.positionX).toBe(124);
        expect(node.positionY).toBe(87);
    });

    it('expose des shapes vue-flow avec statut d’exécution et label de branche', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph(), {
            getNodeStatus: (key) => (key === 'n1' ? 'running' : 'idle'),
            isEdgeFlowing: (id) => id === '11',
        });

        const flowNode = builder.vueFlowNodes.value.find(
            (node) => node.id === 'n1',
        );
        expect(flowNode?.type).toBe('workflow');
        expect(flowNode?.data.status).toBe('running');
        expect(flowNode?.data.definition?.label).toBe('Manuel');

        const branchGraph: WorkflowGraph = {
            nodes: [
                {
                    key: 'a',
                    type: 'logic.condition',
                    name: 'C',
                    config: {},
                    positionX: 0,
                    positionY: 0,
                },
                {
                    key: 'b',
                    type: 'action.email',
                    name: 'E',
                    config: {},
                    positionX: 300,
                    positionY: 0,
                },
            ],
            edges: [
                {
                    id: 12,
                    sourceNodeKey: 'a',
                    targetNodeKey: 'b',
                    sourceHandle: 'false',
                },
            ],
        };
        const branched = useWorkflowBuilder(catalog, branchGraph);
        const edge = branched.vueFlowEdges.value[0];
        expect(edge.label).toBe('false');
        expect(edge.sourceHandle).toBe('false');
    });
});
