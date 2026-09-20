<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    FolderGit2,
    Layers,
    LayoutGrid,
    Workflow,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as templatesIndex } from '@/routes/templates';
import { index as executionsIndex } from '@/routes/workflow-executions';
import { index as workflowsIndex } from '@/routes/workflows';
import type { NavItem } from '@/types';

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

const templatesUrl = computed(() =>
    page.props.currentTeam
        ? templatesIndex({ current_team: page.props.currentTeam.slug }).url
        : '/',
);

/*
 * Nav principale — « Exécutions » ajoutée en phase 10 (lot F) : la page
 * n'était accessible que par détours alors que la maquette la place dans la
 * nav « Pilotage » (sans le badge décoratif de la maquette).
 */
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'Exécutions',
        href: executionsUrl.value,
        icon: Activity,
    },
    {
        title: 'Workflows',
        href: workflowsUrl.value,
        icon: Workflow,
    },
    {
        title: 'Templates',
        href: templatesUrl.value,
        icon: Layers,
    },
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
