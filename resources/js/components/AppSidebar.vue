<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    BookOpen,
    ClipboardList,
    GraduationCap,
    Gamepad2,
    Award,
    MessageSquareText,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import type { NavItem } from '@/types';

const page = usePage();

const isPageVisibleInNav = (key?: string) => {
    if (!key) return true;

    return page.props.studentPageControls?.pages?.[key]?.mode !== 'disabled';
};

const mainNavItems = computed<NavItem[]>(() =>
    [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
            studentPageKey: 'dashboard',
        },
        {
            title: 'My Courses',
            href: '/courses',
            icon: BookOpen,
            studentPageKey: 'courses',
        },
        {
            title: 'Assignments',
            href: '/assignments',
            icon: ClipboardList,
            studentPageKey: 'assignments',
        },
        {
            title: 'Activities',
            href: '/exams',
            icon: GraduationCap,
            studentPageKey: 'exams',
        },
        {
            title: 'Games',
            href: '/games',
            icon: Gamepad2,
            studentPageKey: 'games',
        },
        {
            title: 'Grades',
            href: '/grades',
            icon: Award,
            studentPageKey: 'grades',
        },
        {
            title: 'Chats',
            href: '/chats',
            icon: MessageSquareText,
            studentPageKey: 'chats',
        },
    ].filter((item) => isPageVisibleInNav(item.studentPageKey)),
);

// const footerNavItems: NavItem[] = [
//     {
//         title: 'Repository',
//         href: 'https://github.com/laravel/vue-starter-kit',
//         icon: FolderGit2,
//     },
//     {
//         title: 'Documentation',
//         href: 'https://laravel.com/docs/starter-kits#vue',
//         icon: BookOpen,
//     },
// ];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <slot />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
