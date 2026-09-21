<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    GitBranch,
    KeyRound,
    Layers,
    Lock,
    ShieldCheck,
    Sparkles,
    Users,
    Webhook,
    Zap,
} from '@lucide/vue';
import WelcomeEditorVisual from '@/components/welcome/WelcomeEditorVisual.vue';
import WelcomeLiveCard from '@/components/welcome/WelcomeLiveCard.vue';
import { scrollReveal } from '@/lib/scrollReveal';
import { index as templatesIndex } from '@/routes/templates';

// Directive locale d'apparition au scroll (maquette : v-reveal).
const vReveal = scrollReveal;

const templatesUrl = templatesIndex().url;
</script>

<template>
    <section id="fonctionnalites" class="bento-section">
        <div class="mx-auto max-w-280 px-6">
            <div class="section-head" v-reveal>
                <span class="eyebrow"><Zap :size="13" /> Fonctionnalités</span>
                <h2>Tout ce qu'il faut. Rien de superflu.</h2>
                <p>
                    Un hub complet pour construire, exécuter et superviser vos
                    automatisations — sans multiplier les outils.
                </p>
            </div>

            <div class="bento">
                <!-- Éditeur visuel (large) -->
                <div v-reveal="0" class="b-span2 flex">
                    <div class="b-card">
                        <span
                            class="b-icon"
                            style="
                                background: var(--brand-soft);
                                color: var(--brand-ink);
                            "
                        >
                            <GitBranch :size="18" />
                        </span>
                        <h3>Éditeur visuel</h3>
                        <p>
                            Glissez des nodes, reliez-les, publiez. Graphe
                            interactif, inspecteur, sauvegarde transactionnelle
                            — aucune ligne de code.
                        </p>
                        <div class="b-visual">
                            <WelcomeEditorVisual />
                        </div>
                    </div>
                </div>

                <!-- IA multi-providers -->
                <div v-reveal="70" class="flex">
                    <div class="b-card">
                        <span
                            class="b-icon"
                            style="
                                background: color-mix(
                                    in srgb,
                                    var(--cat-3) 12%,
                                    transparent
                                );
                                color: var(--cat-3);
                            "
                        >
                            <Sparkles :size="18" />
                        </span>
                        <h3>IA multi-providers</h3>
                        <p>
                            OpenAI ou Anthropic en un réglage. Réponses
                            structurées validées, retry automatique, usage en
                            tokens.
                        </p>
                        <div class="b-visual flex flex-wrap gap-2">
                            <span class="mini-chip"
                                ><i style="background: var(--cat-3)"></i
                                >OpenAI</span
                            >
                            <span class="mini-chip"
                                ><i style="background: var(--cat-3)"></i
                                >Anthropic</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Webhooks -->
                <div v-reveal="140" class="flex">
                    <div class="b-card">
                        <span
                            class="b-icon"
                            style="
                                background: color-mix(
                                    in srgb,
                                    var(--cat-2) 12%,
                                    transparent
                                );
                                color: var(--cat-2);
                            "
                        >
                            <Webhook :size="18" />
                        </span>
                        <h3>Webhooks publics</h3>
                        <p>
                            Chaque workflow devient une URL. Idempotent,
                            rate-limité, prêt pour vos formulaires et vos CRM.
                        </p>
                        <div class="b-visual">
                            <span class="code-pill"
                                ><span class="method">202</span> POST
                                /hooks/lead-capture</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Sécurité -->
                <div v-reveal="0" class="flex">
                    <div class="b-card">
                        <span
                            class="b-icon"
                            style="
                                background: var(--success-soft);
                                color: var(--success);
                            "
                        >
                            <ShieldCheck :size="18" />
                        </span>
                        <h3>Sécurisé par défaut</h3>
                        <p>
                            Credentials chiffrés, secrets masqués dans les logs,
                            2FA &amp; passkeys, garde-fous SSRF.
                        </p>
                        <div class="b-visual flex flex-wrap gap-2">
                            <span class="mini-chip"
                                ><Lock :size="11" />AES chiffré</span
                            >
                            <span class="mini-chip"
                                ><KeyRound :size="11" />Passkeys</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Observabilité live (large) -->
                <div v-reveal="70" class="b-span2 flex">
                    <WelcomeLiveCard />
                </div>

                <!-- Équipe -->
                <div v-reveal="140" class="flex">
                    <div class="b-card">
                        <span
                            class="b-icon"
                            style="
                                background: color-mix(
                                    in srgb,
                                    var(--cat-2) 12%,
                                    transparent
                                );
                                color: var(--cat-2);
                            "
                        >
                            <Users :size="18" />
                        </span>
                        <h3>Travail d'équipe</h3>
                        <p>
                            Équipes, invitations, permissions fines. Vos
                            workflows vivent avec votre organisation.
                        </p>
                        <div class="b-visual avatar-stack">
                            <span class="avatar">SF</span>
                            <span
                                class="avatar"
                                style="
                                    background: linear-gradient(
                                        135deg,
                                        var(--cat-2),
                                        var(--cat-5)
                                    );
                                "
                                >LR</span
                            >
                            <span
                                class="avatar"
                                style="
                                    background: linear-gradient(
                                        135deg,
                                        var(--cat-4),
                                        var(--cat-2)
                                    );
                                "
                                >TM</span
                            >
                            <span class="avatar">+4</span>
                        </div>
                    </div>
                </div>

                <!-- Templates (pleine largeur) -->
                <div v-reveal="60" class="b-span4 flex">
                    <div class="b-card">
                        <div class="tpl-strip">
                            <div class="flex max-w-[52ch] flex-col gap-2.5">
                                <span
                                    class="b-icon"
                                    style="
                                        background: var(--brand-soft);
                                        color: var(--brand-ink);
                                    "
                                >
                                    <Layers :size="18" />
                                </span>
                                <h3>Démarrez en un clic</h3>
                                <p>
                                    40+ templates prêts à l'emploi, du lead
                                    capture au rapport hebdomadaire. Dupliquez,
                                    ajustez, exécutez.
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-3.5">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <span class="mini-chip"
                                        ><i style="background: var(--cat-1)"></i
                                        >Lead → CRM</span
                                    >
                                    <span class="mini-chip"
                                        ><i style="background: var(--cat-3)"></i
                                        >Veille concurrentielle IA</span
                                    >
                                    <span class="mini-chip"
                                        ><i style="background: var(--cat-4)"></i
                                        >Rapport hebdomadaire</span
                                    >
                                    <span class="mini-chip"
                                        ><i style="background: var(--cat-2)"></i
                                        >Triage du support</span
                                    >
                                </div>
                                <Link class="tpl-link" :href="templatesUrl">
                                    Parcourir la galerie
                                    <ArrowRight :size="14" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style lang="scss" scoped>
.bento-section {
    padding: 84px 0 8px;
}

.section-head {
    max-width: 620px;
    margin: 0 auto 38px;
    text-align: center;

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 5px 13px;
        border-radius: var(--r-full);
        background: var(--brand-soft);
        color: var(--brand-ink);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    h2 {
        margin: 16px 0 10px;
        font-size: clamp(26px, 3.4vw, 36px);
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    p {
        font-size: 15px;
        color: var(--muted-foreground);
        line-height: 1.6;
    }
}

.bento {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;

    @media (max-width: 960px) {
        grid-template-columns: repeat(2, 1fr);
    }

    @media (max-width: 620px) {
        grid-template-columns: 1fr;
    }
}

.b-span2 {
    grid-column: span 2;
}

.b-span4 {
    grid-column: span 4;

    @media (max-width: 960px) {
        grid-column: span 2;
    }
}

@media (max-width: 620px) {
    .b-span2,
    .b-span4 {
        grid-column: auto;
    }
}

.b-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    padding: 22px;
    overflow: hidden;
    background: var(--card);
    color: var(--card-foreground);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    transition:
        transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1),
        box-shadow 0.25s,
        border-color 0.25s;

    &:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-pop);
    }

    h3 {
        font-size: 16px;
        font-weight: 650;
    }

    p {
        font-size: 13.5px;
        line-height: 1.55;
        color: var(--muted-foreground);
    }
}

.b-icon {
    flex: none;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
}

.b-visual {
    margin-top: auto;
}

.mini-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 11px;
    border-radius: var(--r-full);
    border: 1px solid var(--border);
    background: var(--card);
    font-size: 11.5px;
    font-weight: 500;
    white-space: nowrap;

    i {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
}

.code-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border-radius: var(--r-sm);
    background: var(--secondary);
    border: 1px solid var(--border);
    font-family: var(--font-mono);
    font-size: 11.5px;

    .method {
        color: var(--success);
        font-weight: 600;
    }
}

.avatar-stack {
    display: flex;

    .avatar {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: var(--r-full);
        background: var(--brand-gradient);
        color: var(--brand-foreground);
        font-size: 12px;
        font-weight: 600;
        flex: none;
        user-select: none;
        border: 2px solid var(--card);

        + .avatar {
            margin-left: -9px;
        }
    }
}

.tpl-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    width: 100%;
}

.tpl-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--brand-ink);
    white-space: nowrap;

    &:hover {
        text-decoration: underline;
        text-underline-offset: 3px;
    }
}
</style>
