import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, onScopeDispose, reactive, ref } from 'vue';
import { GitFork, Inbox, Mail, Sparkles, Webhook } from '@lucide/vue';
import {
    type WelcomeDemoEdge,
    type WelcomeDemoEdgeKey,
    type WelcomeDemoEdgeState,
    type WelcomeDemoLogKey,
    type WelcomeDemoNode,
    type WelcomeDemoNodeId,
    type WelcomeDemoNodeState,
} from '@/types/welcome';
import { prefersReducedMotion } from '@/lib/landing';

/*
 * Machine d'états de la démo « Exécution en direct » de la page d'accueil
 * (maquette welcome.html) : boucle d'exécution alternant les branches,
 * paquet voyageant le long des polylignes, journal défilant.
 * Toutes les durées et étapes sont transposées telles quelles.
 */

const WEBHOOK_NODE_COLOR = 'var(--cat-1)';
const AI_NODE_COLOR = 'var(--cat-3)';
const CONDITION_NODE_COLOR = 'var(--cat-5)';
const DATA_NODE_COLOR = 'var(--cat-2)';
const ACTION_NODE_COLOR = 'var(--cat-4)';

/** Nodes cartographiés de la démo (coordonnées viewBox 660×280, maquette). */
const demoNodes: WelcomeDemoNode[] = [
    {
        id: 'webhook',
        x: 16,
        y: 30,
        w: 130,
        h: 58,
        color: WEBHOOK_NODE_COLOR,
        icon: Webhook,
        label: 'Webhook',
        sub: 'lead-capture',
    },
    {
        id: 'ai',
        x: 206,
        y: 30,
        w: 130,
        h: 58,
        color: AI_NODE_COLOR,
        icon: Sparkles,
        label: 'Analyse IA',
        sub: 'claude · gpt',
    },
    {
        id: 'cond',
        x: 396,
        y: 30,
        w: 130,
        h: 58,
        color: CONDITION_NODE_COLOR,
        icon: GitFork,
        label: 'Condition',
        sub: 'ai.category',
    },
    {
        id: 'sort',
        x: 300,
        y: 192,
        w: 130,
        h: 58,
        color: DATA_NODE_COLOR,
        icon: Inbox,
        label: 'Classement',
        sub: 'archive CRM',
    },
    {
        id: 'mail',
        x: 506,
        y: 192,
        w: 140,
        h: 58,
        color: ACTION_NODE_COLOR,
        icon: Mail,
        label: 'E-mail',
        sub: 'réponse auto',
    },
];

const demoEdgeKeys: WelcomeDemoEdgeKey[] = ['ab', 'bc', 'ce', 'cd'];

/** Arêtes polylignes de la démo (chemins + points pour l'animation du paquet). */
const demoEdges: Record<WelcomeDemoEdgeKey, WelcomeDemoEdge> = {
    ab: {
        d: 'M146 59 H206',
        color: WEBHOOK_NODE_COLOR,
        pts: [
            [146, 59],
            [206, 59],
        ],
    },
    bc: {
        d: 'M336 59 H396',
        color: AI_NODE_COLOR,
        pts: [
            [336, 59],
            [396, 59],
        ],
    },
    ce: {
        d: 'M430 88 V150 H365 V192',
        color: CONDITION_NODE_COLOR,
        pts: [
            [430, 88],
            [430, 150],
            [365, 150],
            [365, 192],
        ],
    },
    cd: {
        d: 'M492 88 V168 H576 V192',
        color: CONDITION_NODE_COLOR,
        pts: [
            [492, 88],
            [492, 168],
            [576, 168],
            [576, 192],
        ],
    },
};

/** Journaux simulés de l'exécution démo (maquette WF_LOGS). */
const demoLogs: Record<Exclude<WelcomeDemoLogKey, 'idle'>, string> = {
    webhook: 'webhook.received — POST /hooks/lead-capture (marie@acme.com)',
    ai: 'ai.analyze → catégorie « lead » · confiance 0,97',
    cond: 'condition ai.category == « lead » → branche true',
    mail: 'action.email → réponse envoyée à marie@acme.com',
    sort: 'action.crm → fiche archivée (catégorie « autre »)',
    done: 'Exécution réussie — 5 nodes · 0 erreur',
};

const LOG_PENDING = 'en attente du déclencheur…';

const NODE_IDS: WelcomeDemoNodeId[] = ['webhook', 'ai', 'cond', 'sort', 'mail'];

/** Retour du composable useWelcomeDemo. */
export type UseWelcomeDemoReturn = {
    demoNodes: WelcomeDemoNode[];
    demoEdgeKeys: WelcomeDemoEdgeKey[];
    demoEdges: Record<WelcomeDemoEdgeKey, WelcomeDemoEdge>;
    nodeState: Record<WelcomeDemoNodeId, WelcomeDemoNodeState>;
    edgeState: Record<WelcomeDemoEdgeKey, WelcomeDemoEdgeState>;
    dot: { on: boolean; x: number; y: number };
    runOk: Ref<boolean>;
    runDuration: Ref<string>;
    logIndex: Ref<WelcomeDemoLogKey>;
    currentLog: ComputedRef<string>;
    logDone: ComputedRef<boolean>;
};

export function useWelcomeDemo(): UseWelcomeDemoReturn {
    const nodeState = reactive<Record<WelcomeDemoNodeId, WelcomeDemoNodeState>>(
        {
            webhook: 'pending',
            ai: 'pending',
            cond: 'pending',
            sort: 'pending',
            mail: 'pending',
        },
    );

    const edgeState = reactive<
        Record<WelcomeDemoEdgeKey, WelcomeDemoEdgeState>
    >({
        ab: 'idle',
        bc: 'idle',
        ce: 'idle',
        cd: 'idle',
    });

    const dot = reactive({ on: false, x: 0, y: 0 });
    const runOk = ref(false);
    const runDuration = ref('2,8 s');
    const logIndex = ref<WelcomeDemoLogKey>('idle');

    const currentLog = computed<string>(() => {
        if (logIndex.value === 'idle') {
            return LOG_PENDING;
        }

        return demoLogs[logIndex.value];
    });

    const logDone = computed<boolean>(() => logIndex.value === 'done');

    // ---- Pilotage de la boucle (annulation propre au démontage) ----
    let disposed = false;
    let frameHandle: number | null = null;
    const timers = new Set<number>();

    const wait = (ms: number): Promise<void> =>
        new Promise((resolve) => {
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                resolve();
            }, ms);
            timers.add(timer);
        });

    const setNode = (
        id: WelcomeDemoNodeId,
        state: WelcomeDemoNodeState,
    ): void => {
        nodeState[id] = state;
    };

    const setLog = (key: WelcomeDemoLogKey): void => {
        logIndex.value = key;
    };

    let loop = 0;

    /** Anime le paquet le long d'une arête polyligne (maquette : travel). */
    const travel = (key: WelcomeDemoEdgeKey): Promise<void> =>
        new Promise((resolve) => {
            const segments = demoEdges[key].pts;
            const lengths: number[] = [];
            let total = 0;

            for (let index = 0; index < segments.length - 1; index++) {
                const length = Math.hypot(
                    segments[index + 1][0] - segments[index][0],
                    segments[index + 1][1] - segments[index][1],
                );
                lengths.push(length);
                total += length;
            }

            edgeState[key] = 'live';
            dot.on = true;

            const duration = Math.max(340, total * 1.5);
            const start = performance.now();

            const frame = (now: number): void => {
                if (disposed) {
                    resolve();
                    return;
                }

                const t = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - t, 2);
                let distance = eased * total;
                let index = 0;

                while (
                    index < lengths.length - 1 &&
                    distance > lengths[index]
                ) {
                    distance -= lengths[index];
                    index++;
                }

                const [x0, y0] = segments[index];
                const [x1, y1] = segments[index + 1];
                const ratio = lengths[index] ? distance / lengths[index] : 0;

                dot.x = x0 + (x1 - x0) * ratio;
                dot.y = y0 + (y1 - y0) * ratio;

                if (t < 1) {
                    frameHandle = requestAnimationFrame(frame);
                } else {
                    edgeState[key] = 'done';
                    dot.on = false;
                    resolve();
                }
            };

            frameHandle = requestAnimationFrame(frame);
        });

    const resetDemo = (): void => {
        for (const id of NODE_IDS) {
            nodeState[id] = 'pending';
        }

        for (const key of demoEdgeKeys) {
            edgeState[key] = 'idle';
        }

        runOk.value = false;
        setLog('idle');
    };

    /** Boucle d'exécution de la démo (alterne les branches, maquette runDemo). */
    const runDemo = async (): Promise<void> => {
        const branchTrue = loop % 2 === 0;

        runDuration.value = branchTrue ? '2,8 s' : '2,3 s';
        resetDemo();

        await wait(600);
        if (disposed) return;
        setNode('webhook', 'running');
        setLog('webhook');

        await wait(850);
        if (disposed) return;
        setNode('webhook', 'done');
        await travel('ab');
        if (disposed) return;

        setNode('ai', 'running');
        setLog('ai');
        await wait(1000);
        if (disposed) return;
        setNode('ai', 'done');
        await travel('bc');
        if (disposed) return;

        setNode('cond', 'running');
        setLog('cond');
        await wait(700);
        if (disposed) return;
        setNode('cond', 'done');
        await wait(250);
        if (disposed) return;

        if (branchTrue) {
            await travel('cd');
            if (disposed) return;
            setNode('mail', 'running');
            setLog('mail');
            await wait(800);
            if (disposed) return;
            setNode('mail', 'done');
        } else {
            await travel('ce');
            if (disposed) return;
            setNode('sort', 'running');
            setLog('sort');
            await wait(700);
            if (disposed) return;
            setNode('sort', 'done');
        }

        runOk.value = true;
        setLog('done');

        await wait(2200);
        if (disposed) return;
        loop++;
        void runDemo();
    };

    const startDemo = (): void => {
        if (prefersReducedMotion()) {
            // État final, sans animation (maquette : tous les nodes done)
            for (const id of NODE_IDS) {
                nodeState[id] = 'done';
            }
            runOk.value = true;
            setLog('done');
            return;
        }

        void runDemo();
    };

    onMounted(() => {
        startDemo();
    });

    onScopeDispose(() => {
        disposed = true;

        if (frameHandle !== null) {
            cancelAnimationFrame(frameHandle);
        }

        for (const timer of timers) {
            window.clearTimeout(timer);
        }

        timers.clear();
    });

    return {
        demoNodes,
        demoEdgeKeys,
        demoEdges,
        nodeState,
        edgeState,
        dot,
        runOk,
        runDuration,
        logIndex,
        currentLog,
        logDone,
    };
}
