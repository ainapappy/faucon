<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bot,
    GitBranch,
    History,
    Layers,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AuthWorkflowMotif from '@/components/auth/AuthWorkflowMotif.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import type { AuthBrandPoint, AuthBrandVariant } from '@/types';

const props = defineProps<{
    variant: AuthBrandVariant;
}>();

const appName = usePage().props.name;

const brandPanels: Record<
    AuthBrandVariant,
    {
        headline: [string, string];
        lead: string;
        points: AuthBrandPoint[];
        quote: string[];
        conditionTags: boolean;
    }
> = {
    login: {
        headline: ['Vos automatisations IA,', "orchestrées d'un seul regard."],
        lead: 'Construisez des workflows en graphe — déclencheurs, nodes IA, conditions et actions — exécutés, journalisés et supervisés par votre équipe.',
        points: [
            {
                icon: GitBranch,
                text: 'Éditeur visuel — glissez des nodes, reliez, exécutez',
            },
            {
                icon: Bot,
                text: 'Nodes IA multi-providers avec sorties structurées validées',
            },
            {
                icon: ShieldCheck,
                text: 'Logs audités node par node, secrets chiffrés et masqués',
            },
        ],
        quote: [
            '« Le moteur tourne, vous restez aux commandes. »',
            "— L'équipe Faucon",
        ],
        conditionTags: true,
    },
    register: {
        headline: ['Lancez votre premier', 'workflow en quelques minutes.'],
        lead: "Un compte, une équipe, un espace de travail partagé : modèles prêts à l'emploi, exécutions en un clic, et un moteur qui assume les retries.",
        points: [
            {
                icon: Layers,
                text: 'Galerie de templates pour démarrer sans partir de zéro',
            },
            {
                icon: Users,
                text: 'Travail en équipe avec rôles et invitations',
            },
            {
                icon: History,
                text: 'Historique complet des exécutions, journalisé par node',
            },
        ],
        quote: ["Gratuit pour démarrer · 2FA et clés d'accès incluses"],
        conditionTags: false,
    },
};

const panel = computed(() => brandPanels[props.variant]);
</script>

<template>
    <section class="auth-panel">
        <Link :href="home()" class="auth-brand">
            <AppLogoIcon class="text-brand" />
            {{ appName }}
        </Link>

        <div>
            <AuthWorkflowMotif :condition-tags="panel.conditionTags" />

            <h1 class="anim-in d-2">
                {{ panel.headline[0] }}<br />
                {{ panel.headline[1] }}
            </h1>

            <p class="lead anim-in d-3">
                {{ panel.lead }}
            </p>

            <ul class="auth-points anim-in d-4">
                <li v-for="point in panel.points" :key="point.text">
                    <component :is="point.icon" :size="16" />
                    <span>{{ point.text }}</span>
                </li>
            </ul>
        </div>

        <p class="auth-quote anim-in d-5">
            <template v-for="(line, index) in panel.quote" :key="line">
                <br v-if="index > 0" />
                {{ line }}
            </template>
        </p>
    </section>
</template>
