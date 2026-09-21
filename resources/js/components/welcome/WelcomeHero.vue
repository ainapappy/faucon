<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck, ExternalLink, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import WelcomeDemoCard from '@/components/welcome/WelcomeDemoCard.vue';
import WelcomeStats from '@/components/welcome/WelcomeStats.vue';
import { Button } from '@/components/ui/button';
import { useWelcomeWords } from '@/composables/useWelcomeLanding';
import { dashboard, login, register } from '@/routes';

const page = usePage();

const { words, wordIndex } = useWelcomeWords();

const demoUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : login().url,
);
</script>

<template>
    <section class="hero">
        <div class="hero-glow" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-280 px-6">
            <span class="hero-badge anim-in">
                <Sparkles :size="13" />
                Propulsé par l'IA — nodes OpenAI &amp; Anthropic
            </span>
            <h1 class="anim-in d-1">
                L'automatisation,
                <span class="word">
                    <Transition name="word">
                        <span :key="wordIndex" class="grad-text">{{
                            words[wordIndex]
                        }}</span>
                    </Transition>
                </span>
            </h1>
            <p class="hero-sub anim-in d-2">
                Faucon relie vos outils, exécute vos tâches répétitives et vous
                tient informé. Des workflows visuels, observables node par node,
                sécurisés par défaut.
            </p>
            <div class="anim-in d-3 mt-7 flex flex-wrap justify-center gap-3">
                <Button
                    as-child
                    size="lg"
                    class="bg-brand text-brand-foreground hover:bg-brand-strong shadow-xs"
                >
                    <Link :href="register()">
                        Créer mon premier workflow
                        <ArrowRight :size="16" />
                    </Link>
                </Button>
                <Button as-child variant="outline" size="lg">
                    <Link :href="demoUrl">
                        Voir la démo
                        <ExternalLink :size="15" />
                    </Link>
                </Button>
            </div>
            <div class="hero-proof anim-in d-4">
                <span><CircleCheck :size="14" /> Gratuit pour commencer</span>
                <span><CircleCheck :size="14" /> Sans carte bancaire</span>
                <span><CircleCheck :size="14" /> 2 minutes chrono</span>
            </div>

            <WelcomeStats />
            <WelcomeDemoCard />
        </div>
    </section>
</template>

<style lang="scss" scoped>
.hero {
    position: relative;
    overflow: hidden;
    padding: 84px 0 24px;
    text-align: center;

    @media (max-width: 760px) {
        padding-top: 56px;
    }
}

.hero-glow {
    position: absolute;
    top: -260px;
    left: 50%;
    transform: translateX(-50%);
    width: 1100px;
    height: 640px;
    pointer-events: none;
    background:
        radial-gradient(
            520px 300px at 38% 30%,
            var(--brand-soft),
            transparent 70%
        ),
        radial-gradient(
            480px 280px at 62% 22%,
            color-mix(in srgb, var(--cat-3) 9%, transparent),
            transparent 70%
        );
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 9px;
    border-radius: var(--r-full);
    background: var(--brand-soft);
    color: var(--brand-ink);
    font-size: 11.5px;
    font-weight: 500;
    line-height: 1.6;
    white-space: nowrap;
}

h1 {
    margin: 20px auto 16px;
    max-width: 18ch;
    font-size: clamp(34px, 5.4vw, 56px);
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.06;
}

.grad-text {
    background: var(--brand-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.word {
    display: inline-grid;
    vertical-align: bottom;
    text-align: left;

    > span {
        grid-area: 1 / 1;
        white-space: nowrap;
    }
}

.word-enter-active,
.word-leave-active {
    transition:
        opacity 0.38s ease,
        transform 0.48s cubic-bezier(0.22, 0.9, 0.3, 1.15);
}

.word-enter-from {
    opacity: 0;
    transform: translateY(55%);
}

.word-leave-to {
    opacity: 0;
    transform: translateY(-55%);
}

.hero-sub {
    margin: 0 auto 28px;
    max-width: 56ch;
    font-size: 17px;
    line-height: 1.65;
    color: var(--muted-foreground);
}

.hero-proof {
    display: flex;
    justify-content: center;
    gap: 18px;
    flex-wrap: wrap;
    margin-top: 16px;
    font-size: 12.5px;
    color: var(--muted-foreground);

    span {
        display: inline-flex;
        align-items: center;
        gap: 6px;

        svg {
            color: var(--success);
        }
    }
}

@media (prefers-reduced-motion: reduce) {
    .word-enter-active,
    .word-leave-active {
        transition: none;
    }
}
</style>
