import { describe, expect, it, vi } from 'vitest';
import {
    computeRunOrder,
    useWorkflowSimulation,
} from '@/composables/useWorkflowSimulation';
import type { NodeTypeCatalog } from '@/types';

/* Losange : a → b → d, a → c → d — l'ordre BFS doit lire a, b, c, d. */
const diamondNodes = [
    { key: 'a', type: 'trigger.manual', name: 'Déclencheur' },
    { key: 'b', type: 'logic.condition', name: 'Condition' },
    { key: 'c', type: 'ai.generation', name: 'Génération' },
    { key: 'd', type: 'action.email', name: 'Email' },
];
const diamondEdges = [
    { id: 'e1', sourceNodeKey: 'a', targetNodeKey: 'b', sourceHandle: 'out' },
    { id: 'e2', sourceNodeKey: 'a', targetNodeKey: 'c', sourceHandle: 'out' },
    { id: 'e3', sourceNodeKey: 'b', targetNodeKey: 'd', sourceHandle: 'true' },
    { id: 'e4', sourceNodeKey: 'c', targetNodeKey: 'd', sourceHandle: 'out' },
];

describe('computeRunOrder', () => {
    it('parcourt le losange en BFS depuis les sources', () => {
        const order = computeRunOrder(diamondNodes, diamondEdges);
        expect(order.map((node) => node.key)).toEqual(['a', 'b', 'c', 'd']);
    });

    it('traite tout node sans entrée comme source, en respectant les dépendances', () => {
        // « x → y » est une chaîne déconnectée du losange : x est une source
        // au même titre que a, mais y reste après x et d après b et c.
        const nodes = [
            ...diamondNodes,
            { key: 'x', type: 'trigger.manual', name: 'Isolé' },
            { key: 'y', type: 'action.email', name: 'Orphelin' },
        ];
        const edges = [
            ...diamondEdges,
            {
                id: 'e5',
                sourceNodeKey: 'x',
                targetNodeKey: 'y',
                sourceHandle: 'out',
            },
        ];
        const keys = computeRunOrder(nodes, edges).map((node) => node.key);
        expect(new Set(keys)).toEqual(new Set(['a', 'b', 'c', 'd', 'x', 'y']));
        expect(keys.indexOf('b')).toBeLessThan(keys.indexOf('d'));
        expect(keys.indexOf('c')).toBeLessThan(keys.indexOf('d'));
        expect(keys.indexOf('x')).toBeLessThan(keys.indexOf('y'));
    });

    it('place les nodes pris dans un cycle en fin de parcours', () => {
        const nodes = [
            ...diamondNodes,
            { key: 'u', type: 'data.input', name: 'U' },
            { key: 'v', type: 'data.transform', name: 'V' },
        ];
        const edges = [
            ...diamondEdges,
            {
                id: 'e5',
                sourceNodeKey: 'u',
                targetNodeKey: 'v',
                sourceHandle: 'out',
            },
            {
                id: 'e6',
                sourceNodeKey: 'v',
                targetNodeKey: 'u',
                sourceHandle: 'out',
            },
        ];
        const keys = computeRunOrder(nodes, edges).map((node) => node.key);
        expect(keys.slice(4)).toEqual(['u', 'v']);
    });

    it('gère un graphe sans arête', () => {
        const order = computeRunOrder(diamondNodes, []);
        expect(order).toHaveLength(4);
    });
});

describe('useWorkflowSimulation', () => {
    const catalog: NodeTypeCatalog = {
        'trigger.manual': {
            type: 'trigger.manual',
            category: 'trigger',
            label: 'Manuel',
            description: '',
            icon: 'play',
            input: false,
            outputs: [{ id: 'out', label: null, position: 0.5 }],
            fields: [],
        },
        'logic.condition': {
            type: 'logic.condition',
            category: 'logic',
            label: 'Condition',
            description: '',
            icon: 'git-branch',
            input: true,
            outputs: [
                { id: 'true', label: 'true', position: 0.36 },
                { id: 'false', label: 'false', position: 0.68 },
            ],
            fields: [],
        },
        'ai.generation': {
            type: 'ai.generation',
            category: 'ai',
            label: 'Génération',
            description: '',
            icon: 'sparkles',
            input: true,
            outputs: [{ id: 'out', label: null, position: 0.5 }],
            fields: [],
        },
        'action.email': {
            type: 'action.email',
            category: 'action',
            label: 'Email',
            description: '',
            icon: 'mail',
            input: true,
            outputs: [],
            fields: [],
        },
    };

    function createSimulation(nodes = diamondNodes, edges = diamondEdges) {
        const onEmpty = vi.fn();
        const simulation = useWorkflowSimulation({
            getNodes: () => nodes,
            getEdges: () => edges,
            getDefinition: (type) => catalog[type],
            onEmpty,
            wait: () => Promise.resolve(),
            random: () => 0.5,
        });
        return { simulation, onEmpty };
    }

    it('exécute tous les nodes et produit un résumé', async () => {
        const { simulation } = createSimulation();

        expect(simulation.running.value).toBe(false);
        await simulation.start();

        expect(simulation.running.value).toBe(false);
        expect(simulation.summary.value).toEqual({
            ok: true,
            nodes: 4,
            durationMs: expect.any(Number),
        });

        const statuses = simulation.statuses.value;
        expect(statuses).toEqual({ a: 'ok', b: 'ok', c: 'ok', d: 'ok' });

        const messages = simulation.logs.value.map((entry) => entry.message);
        expect(
            messages.some((message) =>
                message.includes('Validation du graphe'),
            ),
        ).toBe(true);
        expect(
            messages.some((message) => message.includes('simulation locale')),
        ).toBe(true);
        // Node IA : la sortie est annoncée comme aperçu simulé.
        expect(
            messages.some((message) => message.includes('Sortie simulée')),
        ).toBe(true);
        // Résumé final.
        expect(messages[messages.length - 1]).toContain('Exécution terminée');
    });

    it("journalise l'évaluation de la première branche d'un node multi-sorties", async () => {
        const { simulation } = createSimulation();
        await simulation.start();

        const branchLogs = simulation.logs.value.filter((entry) =>
            entry.message.includes('branche'),
        );
        expect(branchLogs).toHaveLength(1);
        expect(branchLogs[0]?.message).toContain('true');
        expect(branchLogs[0]?.source).toBe('Condition');
    });

    it('traite le graphe vide comme un cas nominal journalisé, sans erreur', async () => {
        const { simulation, onEmpty } = createSimulation([], []);

        await simulation.start();

        expect(simulation.running.value).toBe(false);
        expect(onEmpty).toHaveBeenCalledTimes(1);
        expect(simulation.summary.value).toBeNull();
        expect(
            simulation.logs.value.some((entry) =>
                entry.message.includes('graphe est vide'),
            ),
        ).toBe(true);
    });

    it('ne relance pas une exécution déjà en cours', async () => {
        let release!: () => void;
        const gate = new Promise<void>((resolve) => {
            release = resolve;
        });
        const simulation = useWorkflowSimulation({
            getNodes: () => diamondNodes,
            getEdges: () => diamondEdges,
            getDefinition: (type) => catalog[type],
            wait: () => gate,
            random: () => 0.5,
        });

        const first = simulation.start();
        expect(simulation.running.value).toBe(true);

        await simulation.start(); // ignoré

        release();
        await first;
        expect(simulation.running.value).toBe(false);
    });
});
