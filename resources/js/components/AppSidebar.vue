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
    CalendarDays,
} from 'lucide-vue-next';
import { computed, useSlots } from 'vue';
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
const slots = useSlots();
const hasChatNavigation = computed(() => Boolean(slots['chat-navigation']));

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
            title: 'Activities Hub',
            href: '/activities',
            icon: GraduationCap,
            studentPageKey: 'exams',
        },
        {
            title: 'Calendar',
            href: '/calendar',
            icon: CalendarDays,
            studentPageKey: 'calendar',
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
    ].filter(
        (item) =>
            isPageVisibleInNav(item.studentPageKey) &&
            (item.studentPageKey !== 'chats' || !hasChatNavigation.value),
    ),
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
            <slot name="chat-navigation" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
