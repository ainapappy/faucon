import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, onScopeDispose, reactive, ref } from 'vue';
import {
    Activity,
    CircleCheck,
    Clock,
    Mail,
    Sparkles,
    Webhook,
} from '@lucide/vue';
import type { WelcomeFeedItem } from '@/types/welcome';
import {
    landingAreaPath,
    landingLinePath,
    prefersReducedMotion,
} from '@/lib/landing';

/*
 * Comportements de la page d'accueil « welcome » (maquette welcome.html) :
 * - useWelcomeWords : rotation du mot du titre H1 (2,7 s, stoppée si
 *   l'onglet est masqué ou en mouvement réduit) ;
 * - useWelcomeStats : chiffres clés du héros en count-up (valeurs finales
 *   en mouvement réduit) ;
 * - useWelcomeScrolled : ombre de l'en-tête au scroll ;
 * - useWelcomeLive : carte observabilité — tracé du mini-graphe au premier
 *   passage à l'écran + flux d'activité simulé (maquette FEED_POOL).
 */

/** Mots rotatifs du H1 (maquette). */
const WORDS = ['sans effort.', 'sans code.', "avec l'IA.", 'en équipe.'];

const WORD_INTERVAL_MS = 2700;

/** Valeurs finales des chiffres clés du héros (maquette). */
const FINAL_STATS = { nodes: 17, tpl: 40, avg: 2.8, logs: 100 };

/** Série du mini-graphe d'observabilité (viewBox 260×72, maquette). */
const CHART_POINTS = [4, 6, 5, 8, 7, 10, 9, 12, 11, 14, 13, 16];

/** Flux d'activité simulé de la carte observabilité (maquette FEED_POOL). */
const FEED_POOL: Array<Omit<WelcomeFeedItem, 'id' | 'time'>> = [
    {
        icon: CircleCheck,
        color: 'var(--success)',
        text: '« Veille concurrentielle » — succès en 3,9 s',
    },
    {
        icon: Webhook,
        color: 'var(--cat-1)',
        text: 'Webhook /lead-capture reçu — run #4813',
    },
    {
        icon: Sparkles,
        color: 'var(--cat-3)',
        text: 'Résumé IA généré (312 tokens)',
    },
    {
        icon: Mail,
        color: 'var(--cat-4)',
        text: 'Rapport hebdo envoyé à 14 destinataires',
    },
    {
        icon: Clock,
        color: 'var(--info)',
        text: '« Rapport hebdo » planifié — 0 9 * * 1',
    },
    {
        icon: Activity,
        color: 'var(--cat-5)',
        text: '« Triage support » — 42 tickets classés',
    },
];

const FEED_SIZE = 3;
const FEED_INTERVAL_MS = 2600;
const RECENT_LABEL = "à l'instant";
const REDUCED_LABEL = 'il y a 2 min';

/** Mots rotatifs du titre : words + index courant. */
export function useWelcomeWords(): { words: string[]; wordIndex: Ref<number> } {
    const wordIndex = ref(0);
    let interval: number | undefined;

    onMounted(() => {
        if (prefersReducedMotion()) {
            return;
        }

        interval = window.setInterval(() => {
            if (!document.hidden) {
                wordIndex.value = (wordIndex.value + 1) % WORDS.length;
            }
        }, WORD_INTERVAL_MS);
    });

    onScopeDispose(() => {
        window.clearInterval(interval);
    });

    return { words: WORDS, wordIndex };
}

/** Chiffres clés du héros, animés en count-up (ease-out cubique, maquette). */
export function useWelcomeStats(): {
    stats: { nodes: number; tpl: number; avg: number; logs: number };
} {
    const stats = reactive({ nodes: 0, tpl: 0, avg: 0, logs: 0 });
    const frames = new Set<number>();
    const timers = new Set<number>();

    const countUp = (
        key: keyof typeof FINAL_STATS,
        to: number,
        { duration = 900, decimals = 0 } = {},
    ): void => {
        const start = performance.now();
        const ease = (t: number): number => 1 - Math.pow(1 - t, 3);

        const frame = (now: number): void => {
            const t = Math.min(1, (now - start) / duration);
            stats[key] = Number((to * ease(t)).toFixed(decimals));

            if (t < 1) {
                const handle = requestAnimationFrame(frame);
                frames.add(handle);
            } else {
                frames.clear();
            }
        };

        const handle = requestAnimationFrame(frame);
        frames.add(handle);
    };

    onMounted(() => {
        if (prefersReducedMotion()) {
            Object.assign(stats, FINAL_STATS);
            return;
        }

        countUp('nodes', FINAL_STATS.nodes);

        const delayed = window.setTimeout(
            () => countUp('tpl', FINAL_STATS.tpl),
            120,
        );
        timers.add(delayed);
        const delayedAvg = window.setTimeout(
            () => countUp('avg', FINAL_STATS.avg, { decimals: 1 }),
            240,
        );
        timers.add(delayedAvg);
        const delayedLogs = window.setTimeout(
            () => countUp('logs', FINAL_STATS.logs),
            360,
        );
        timers.add(delayedLogs);
    });

    onScopeDispose(() => {
        for (const handle of frames) {
            cancelAnimationFrame(handle);
        }
        frames.clear();

        for (const timer of timers) {
            window.clearTimeout(timer);
        }
        timers.clear();
    });

    return { stats };
}

/** État « en-tête défilé » (ombre + bordure, maquette : scrolled). */
export function useWelcomeScrolled(): { scrolled: Ref<boolean> } {
    const scrolled = ref(false);

    const onScroll = (): void => {
        scrolled.value = window.scrollY > 8;
    };

    onMounted(() => {
        window.addEventListener('scroll', onScroll, { passive: true });
    });

    onScopeDispose(() => {
        window.removeEventListener('scroll', onScroll);
    });

    return { scrolled };
}

/** Carte observabilité : tracé du graphe + flux d'activité simulé. */
export function useWelcomeLive(target: Ref<HTMLElement | null>): {
    chartDrawn: Ref<boolean>;
    chartPaths: ComputedRef<{ area: string; line: string }>;
    feed: Ref<WelcomeFeedItem[]>;
} {
    const chartDrawn = ref(false);
    const feed = ref<WelcomeFeedItem[]>([]);

    let feedTick = 0;
    let feedId = 0;
    let started = false;
    let interval: number | undefined;
    let observer: IntersectionObserver | null = null;

    const chartPaths = computed(() => ({
        area: landingAreaPath(CHART_POINTS, 260, 72),
        line: landingLinePath(CHART_POINTS, 260, 72),
    }));

    const pushFeed = (): void => {
        const source = FEED_POOL[feedTick % FEED_POOL.length];
        feedTick++;
        feed.value.unshift({ ...source, id: feedId++, time: RECENT_LABEL });

        if (feed.value.length > FEED_SIZE) {
            feed.value.pop();
        }
    };

    const startLive = (): void => {
        if (started) {
            return;
        }

        started = true;
        chartDrawn.value = true;

        if (prefersReducedMotion()) {
            feed.value = FEED_POOL.slice(0, FEED_SIZE).map((item, index) => ({
                ...item,
                id: index,
                time: REDUCED_LABEL,
            }));
            return;
        }

        for (let index = 0; index < FEED_SIZE; index++) {
            pushFeed();
        }

        interval = window.setInterval(() => {
            if (!document.hidden) {
                pushFeed();
            }
        }, FEED_INTERVAL_MS);
    };

    onMounted(() => {
        if (target.value && 'IntersectionObserver' in window) {
            observer = new IntersectionObserver(
                (entries) => {
                    for (const entry of entries) {
                        if (entry.isIntersecting) {
                            startLive();
                            observer?.disconnect();
                        }
                    }
                },
                { threshold: 0.3 },
            );
            observer.observe(target.value);
        } else {
            startLive();
        }
    });

    onScopeDispose(() => {
        observer?.disconnect();
        window.clearInterval(interval);
    });

    return { chartDrawn, chartPaths, feed };
}
