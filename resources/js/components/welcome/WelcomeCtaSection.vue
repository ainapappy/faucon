<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { scrollReveal } from '@/lib/scrollReveal';
import { index as templatesIndex } from '@/routes/templates';
import { register } from '@/routes';

// Directive locale d'apparition au scroll (maquette : v-reveal).
const vReveal = scrollReveal;

const templatesUrl = templatesIndex().url;
</script>

<template>
    <section class="cta-section">
        <div class="mx-auto max-w-280 px-6">
            <div class="cta-panel" v-reveal>
                <h2>Laissez le faucon veiller.</h2>
                <p>
                    Vos tâches répétitives deviennent des workflows qui tournent
                    seuls — pendant que vous avancez sur l'essentiel.
                </p>
                <div class="cta-actions">
                    <Button as-child size="lg" class="cta-primary shadow-none">
                        <Link :href="register()">
                            Créer mon compte gratuitement
                            <ArrowRight :size="16" />
                        </Link>
                    </Button>
                    <Button as-child size="lg" class="cta-ghost shadow-none">
                        <Link :href="templatesUrl">Explorer les templates</Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>

<style lang="scss" scoped>
.cta-section {
    padding: 88px 0 0;
}

.cta-panel {
    position: relative;
    overflow: hidden;
    text-align: center;
    padding: clamp(40px, 6vw, 64px) 28px;
    border-radius: 24px;
    background: var(--brand-gradient);
    color: var(--brand-foreground);
    box-shadow: var(--shadow-pop);

    &::before,
    &::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        pointer-events: none;
        background: radial-gradient(
            closest-side,
            hsl(0 0% 100% / 0.28),
            transparent
        );
        animation: welcome-glow-drift 9s ease-in-out infinite;
    }

    &::before {
        width: 420px;
        height: 420px;
        top: -220px;
        left: -120px;
    }

    &::after {
        width: 380px;
        height: 380px;
        bottom: -220px;
        right: -100px;
        animation-delay: -4.5s;
    }

    h2 {
        position: relative;
        font-size: clamp(26px, 3.6vw, 38px);
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    p {
        position: relative;
        margin: 12px auto 26px;
        max-width: 46ch;
        font-size: 15px;
        opacity: 0.85;
    }
}

.cta-actions {
    position: relative;
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.cta-primary {
    background: hsl(0 0% 100%);
    color: hsl(26 83% 12%);
    box-shadow: 0 8px 22px -8px hsl(26 83% 8% / 0.55);

    &:hover {
        background: hsl(0 0% 96%);
    }
}

.cta-ghost {
    border: 1px solid hsl(26 83% 12% / 0.35);
    color: inherit;

    &:hover {
        background: hsl(26 83% 12% / 0.08);
    }
}

@keyframes welcome-glow-drift {
    0%,
    100% {
        transform: translate(0, 0);
    }

    50% {
        transform: translate(26px, 14px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cta-panel::before,
    .cta-panel::after {
        animation: none;
    }
}
</style>
