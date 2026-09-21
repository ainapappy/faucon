<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard } from '@/routes';
import { index as executionsIndex } from '@/routes/workflow-executions';
import { index as templatesIndex } from '@/routes/templates';
import { index as workflowsIndex } from '@/routes/workflows';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const workflowsUrl = computed(() =>
    page.props.currentTeam
        ? workflowsIndex({ current_team: page.props.currentTeam.slug }).url
        : '/',
);

const executionsUrl = computed(() =>
    page.props.currentTeam
        ? executionsIndex({ current_team: page.props.currentTeam.slug }).url
        : '/',
);

const templatesUrl = computed(() => templatesIndex().url);
</script>

<template>
    <footer class="site-footer">
        <div class="mx-auto max-w-280 px-6">
            <div class="footer-grid">
                <div>
                    <div class="flex items-center gap-2.5 text-base font-bold">
                        <AppLogoIcon class="size-6" />
                        Faucon
                        <span class="hub-badge">AI Workflow Hub</span>
                    </div>
                    <p
                        class="text-muted-foreground mt-3 max-w-[34ch] text-[13.5px] leading-[1.6]"
                    >
                        Créez, exécutez et observez des workflows automatisés
                        propulsés par l'IA.
                    </p>
                </div>
                <div class="footer-col">
                    <h4>Démo</h4>
                    <Link :href="dashboardUrl">Tableau de bord</Link>
                    <Link :href="workflowsUrl">Workflows</Link>
                    <Link :href="workflowsUrl">Éditeur</Link>
                    <Link :href="executionsUrl">Exécutions</Link>
                </div>
                <div class="footer-col">
                    <h4>Ressources</h4>
                    <Link :href="templatesUrl">Templates</Link>
                </div>
                <div class="footer-col">
                    <h4>Ainatrix</h4>
                    <a
                        href="https://www.ainatrix.com"
                        target="_blank"
                        rel="noopener"
                        >ainatrix.com</a
                    >
                    <a href="mailto:nyaina@ainatrix.com">nyaina@ainatrix.com</a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2026 Faucon · MIT</span>
                <a
                    class="ainatrix"
                    href="https://www.ainatrix.com"
                    target="_blank"
                    rel="noopener"
                >
                    <Sparkles :size="14" />
                    Conçu &amp; développé par Ainatrix
                </a>
            </div>
        </div>
    </footer>
</template>

<style lang="scss" scoped>
.site-footer {
    margin-top: 88px;
    border-top: 1px solid var(--border);
    padding: 44px 0 28px;
}

.footer-grid {
    display: grid;
    grid-template-columns: 2.2fr 1fr 1fr 1.2fr;
    gap: 28px;

    @media (max-width: 960px) {
        grid-template-columns: 1fr 1fr;
    }

    @media (max-width: 620px) {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

.hub-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: var(--r-full);
    border: 1px solid var(--border);
    color: var(--foreground);
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}

.footer-col {
    h4 {
        margin-bottom: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--muted-foreground);
    }

    a {
        display: block;
        padding: 4px 0;
        font-size: 13.5px;
        color: var(--muted-foreground);
        transition: color 0.2s;

        &:hover {
            color: var(--foreground);
        }
    }
}

.footer-bottom {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 36px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    font-size: 12.5px;
    color: var(--muted-foreground);
}

.ainatrix {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-left: auto;
    font-weight: 600;
    color: var(--foreground);

    svg {
        color: var(--brand);
    }

    &:hover {
        text-decoration: underline;
        text-underline-offset: 3px;
    }
}
</style>
