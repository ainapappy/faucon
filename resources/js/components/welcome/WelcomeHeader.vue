<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { useWelcomeScrolled } from '@/composables/useWelcomeLanding';
import { dashboard, login, register } from '@/routes';
import { index as templatesIndex } from '@/routes/templates';

const page = usePage();

const { scrolled } = useWelcomeScrolled();
const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

const toggleTheme = (): void => {
    updateAppearance(isDark.value ? 'light' : 'dark');
};

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const templatesUrl = computed(() => templatesIndex().url);
</script>

<template>
    <header class="site-header" :class="{ scrolled }">
        <div
            class="header-inner mx-auto flex h-15.5 max-w-280 items-center gap-5 px-6"
        >
            <Link
                href="/"
                class="flex items-center gap-2.5 text-[17px] font-bold tracking-[-0.02em]"
            >
                <AppLogoIcon class="size-6.5" />
                Faucon
            </Link>
            <nav class="header-nav ml-2.5 flex gap-0.5">
                <a href="#fonctionnalites">Fonctionnalités</a>
                <a href="#demo">Démo</a>
                <Link :href="templatesUrl">Templates</Link>
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="isDark ? 'Thème clair' : 'Thème sombre'"
                    class="cursor-pointer"
                    @click="toggleTheme"
                >
                    <Sun v-if="isDark" :size="17" />
                    <Moon v-else :size="17" />
                </Button>
                <template v-if="$page.props.auth.user">
                    <Button as-child size="sm">
                        <Link :href="dashboardUrl">Tableau de bord</Link>
                    </Button>
                </template>
                <template v-else>
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="login()">Se connecter</Link>
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        class="bg-brand text-brand-foreground hover:bg-brand-strong"
                    >
                        <Link :href="register()">Commencer</Link>
                    </Button>
                </template>
            </div>
        </div>
    </header>
</template>

<style lang="scss" scoped>
.site-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: color-mix(in srgb, var(--background) 82%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid transparent;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;

    &.scrolled {
        border-bottom-color: var(--border);
        box-shadow: var(--shadow-sm);
    }
}

.header-nav a {
    padding: 7px 12px;
    border-radius: var(--r-sm);
    font-size: 13.5px;
    color: var(--muted-foreground);
    transition:
        color 0.2s,
        background 0.2s;

    &:hover {
        color: var(--foreground);
        background: var(--accent);
    }
}

@media (max-width: 760px) {
    .header-nav {
        display: none;
    }
}
</style>
