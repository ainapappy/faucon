import { describe, expect, it } from 'vitest';
import {
    defaultConfigValue,
    newNodeKey,
    useWorkflowBuilder,
} from '@/composables/useWorkflowBuilder';
import type { NodeField, NodeTypeCatalog, WorkflowGraph } from '@/types';

/* Champs AI partagés par les 5 modes (D12) — select Modèle config-driven, fake d'abord. */
const AI_MODEL_OPTIONS = [
    'fake/demo',
    'openai/gpt-4o-mini',
    'openai/gpt-4o',
    'anthropic/claude-haiku-4-5',
    'anthropic/claude-sonnet-5',
    'anthropic/claude-opus-5',
];

function modelField(): NodeField {
    return {
        key: 'model',
        label: 'Modèle',
        type: 'select',
        required: false,
        placeholder: null,
        options: AI_MODEL_OPTIONS,
        min: null,
        max: null,
        step: null,
        mono: false,
    };
}

function temperatureField(): NodeField {
    return {
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
    };
}

function maxTokensField(): NodeField {
    return {
        key: 'max_tokens',
        label: 'Max tokens',
        type: 'text',
        required: false,
        placeholder: null,
        options: null,
        min: null,
        max: null,
        step: null,
        mono: true,
    };
}

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
    /*
     * Catalogue AI (phase 6, D12) — miroir exact des 5 définitions du
     * NodeCatalog backend, options de modèle composites config-driven
     * (fake d'abord : un test-run ne dépense jamais un appel réel).
     * `ai.summary` n'existe plus : les modes sont prompt, classification,
     * extraction, summarization, generation.
     */
    'ai.prompt': {
        type: 'ai.prompt',
        category: 'ai',
        label: 'Prompt',
        description: 'Interroge un modèle IA avec un prompt libre',
        icon: 'pen-line',
        input: true,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [
            modelField(),
            {
                key: 'prompt',
                label: 'Prompt',
                type: 'textarea',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            temperatureField(),
            maxTokensField(),
        ],
    },
    'ai.classification': {
        type: 'ai.classification',
        category: 'ai',
        label: 'Classification',
        description: 'Catégorise un contenu (sortie structurée)',
        icon: 'bot',
        input: true,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [
            modelField(),
            {
                key: 'prompt',
                label: 'Prompt',
                type: 'textarea',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            {
                key: 'labels',
                label: 'Étiquettes',
                type: 'text',
                required: false,
                placeholder: 'lead, spam, question',
                options: null,
                min: null,
                max: null,
                step: null,
                mono: true,
            },
            temperatureField(),
            maxTokensField(),
        ],
    },
    'ai.extraction': {
        type: 'ai.extraction',
        category: 'ai',
        label: 'Extraction',
        description: 'Extrait des champs structurés (JSON) d’un contenu',
        icon: 'scan-text',
        input: true,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [
            modelField(),
            {
                key: 'prompt',
                label: 'Contenu à analyser',
                type: 'textarea',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            {
                key: 'fields',
                label: 'Champs (Clé: type, une par ligne)',
                type: 'textarea',
                required: false,
                placeholder: 'nom: text\nmontant: number',
                options: null,
                min: null,
                max: null,
                step: null,
                mono: true,
            },
            temperatureField(),
            maxTokensField(),
        ],
    },
    'ai.summarization': {
        type: 'ai.summarization',
        category: 'ai',
        label: 'Résumé',
        description: 'Condense un contenu long',
        icon: 'file-text',
        input: true,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [
            modelField(),
            {
                key: 'prompt',
                label: 'Consigne',
                type: 'textarea',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            temperatureField(),
            maxTokensField(),
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
            modelField(),
            {
                key: 'prompt',
                label: 'Instructions',
                type: 'textarea',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            temperatureField(),
            maxTokensField(),
        ],
    },
    'action.http': {
        type: 'action.http',
        category: 'action',
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
                options: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
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
                placeholder: 'https://api.exemple.com/v1/…',
                options: null,
                min: null,
                max: null,
                step: null,
                mono: true,
            },
            {
                key: 'headers',
                label: 'En-têtes',
                type: 'textarea',
                required: false,
                placeholder: 'Content-Type: application/json',
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
                mono: true,
            },
            {
                key: 'integration_id',
                label: 'Intégration',
                type: 'integration',
                required: false,
                placeholder: null,
                options: null,
                min: null,
                max: null,
                step: null,
                mono: false,
            },
            {
                key: 'failure_policy',
                label: 'Politique d’échec',
                type: 'select',
                required: false,
                placeholder: null,
                options: ['fail', 'continue'],
                min: null,
                max: null,
                step: null,
                mono: false,
            },
        ],
    },
    'trigger.webhook': {
        type: 'trigger.webhook',
        category: 'trigger',
        label: 'Webhook',
        description: 'Appel HTTP entrant, idempotent et rate-limité',
        icon: 'webhook',
        input: false,
        outputs: [{ id: 'out', label: null, position: 0.5 }],
        fields: [],
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
        const field = catalog['action.http'].fields[0];
        expect(defaultConfigValue(field)).toBe('GET');
    });

    it('initialise failure_policy sur fail (première option)', () => {
        const field = catalog['action.http'].fields[5];
        expect(field.key).toBe('failure_policy');
        expect(defaultConfigValue(field)).toBe('fail');
    });

    it('initialise un champ integration à une chaîne vide (référence choisie, pas d’option)', () => {
        const field = catalog['action.http'].fields[4];
        expect(field.type).toBe('integration');
        expect(defaultConfigValue(field)).toBe('');
    });

    it('initialise un range au milieu du segment', () => {
        const field = catalog['ai.generation'].fields.find(
            (candidate) => candidate.key === 'temperature',
        );
        expect(field).toBeDefined();
        expect(defaultConfigValue(field as NodeField)).toBe(0.5);
    });

    it('initialise le select Modèle des nodes IA sur la première option (fake/demo)', () => {
        const field = catalog['ai.generation'].fields.find(
            (candidate) => candidate.key === 'model',
        );
        expect(field?.options?.[0]).toBe('fake/demo');
        expect(defaultConfigValue(field as NodeField)).toBe('fake/demo');
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

    it('ajoute un node webhook sans aucun champ de configuration (section dédiée à l’inspecteur)', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());

        const node = builder.addNode('trigger.webhook', { x: 200, y: 200 });
        expect(node).not.toBeNull();
        expect(node?.name).toBe('Webhook');
        expect(node?.config).toEqual({});
    });

    it('ajoute un node ai.classification avec ses cinq champs initialisés du schéma', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());

        const node = builder.addNode('ai.classification', { x: 200, y: 200 });
        expect(node).not.toBeNull();
        expect(node?.config).toEqual({
            model: 'fake/demo',
            prompt: '',
            labels: '',
            temperature: 0.5,
            max_tokens: '',
        });
    });

    it('expose les cinq modes AI du catalogue miroir (ai.summary n’existe plus)', () => {
        const aiTypes = Object.keys(catalog).filter((type) =>
            type.startsWith('ai.'),
        );
        expect(aiTypes).toEqual([
            'ai.prompt',
            'ai.classification',
            'ai.extraction',
            'ai.summarization',
            'ai.generation',
        ]);
        expect(catalog['ai.summary']).toBeUndefined();
    });

    it('ajoute un node action.http avec les six champs du catalogue initialisés', () => {
        const builder = useWorkflowBuilder(catalog, buildGraph());

        const node = builder.addNode('action.http', { x: 200, y: 200 });
        expect(node).not.toBeNull();
        expect(node?.config).toEqual({
            method: 'GET',
            url: '',
            headers: '',
            body: '',
            integration_id: '',
            failure_policy: 'fail',
        });
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
