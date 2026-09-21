<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WelcomeCtaSection from '@/components/welcome/WelcomeCtaSection.vue';
import WelcomeFeaturesSection from '@/components/welcome/WelcomeFeaturesSection.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import WelcomeSiteFooter from '@/components/welcome/WelcomeSiteFooter.vue';
</script>

<!--
    Page d'accueil publique (maquette .knowledge/design/welcome.html),
    rendue sans layout (app.ts). Assembleur : chaque section vit dans
    son composant (components/welcome/*), les comportements animés dans
    composables/useWelcomeDemo et useWelcomeLanding.
-->
<template>
    <div>
        <Head title="Faucon — L'automatisation, sans effort">
            <meta
                name="description"
                content="Faucon — AI Workflow Hub. Créez visuellement des workflows propulsés par l'IA, exécutez-les à l'échelle, observez chaque exécution node par node."
            />
        </Head>
        <WelcomeHeader />
        <main>
            <WelcomeHero />
            <WelcomeFeaturesSection />
            <WelcomeCtaSection />
        </main>
        <WelcomeSiteFooter />
    </div>
</template>
