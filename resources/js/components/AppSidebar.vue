<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    Building2,
    Library,
    ClipboardList,
    LayoutGrid,
    Search,
    Users,
} from '@lucide/vue';
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
import { index as questionBank } from '@/routes/assessment/questions';
import { index as results } from '@/routes/assessment/results';
import { index as activities } from '@/routes/activities';
import { catalogue, index as courses } from '@/routes/courses';
import { edit as organizationSettings } from '@/routes/organization/settings';
import { index as usersIndex } from '@/routes/users';
import type { Auth, NavItem } from '@/types';

const page = usePage<{ auth: Auth }>();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'ui.nav.dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'courses.nav.courses',
        href: courses(),
        icon: BookOpen,
    },
    // Activities of the own course spaces (UC-022), for teachers and students.
    ...(page.props.auth.user?.role !== 'org_admin'
        ? [{ title: 'activities.nav', href: activities(), icon: ClipboardList }]
        : []),
    ...(page.props.auth.user?.role === 'student'
        ? [
              {
                  title: 'courses.nav.catalogue',
                  href: catalogue(),
                  icon: Search,
              },
              // My results (UC-017).
              {
                  title: 'assessment.nav.results',
                  href: results(),
                  icon: Award,
              },
          ]
        : []),
    // Personal question bank (UC-023, OWN-016), for teachers only.
    ...(page.props.auth.user?.role === 'teacher'
        ? [
              {
                  title: 'assessment.nav.question_bank',
                  href: questionBank(),
                  icon: Library,
              },
          ]
        : []),
    // User management (UC-032), for org admins only.
    ...(page.props.auth.user?.role === 'org_admin'
        ? [{ title: 'users.nav', href: usersIndex(), icon: Users }]
        : []),
    // Organisation settings (UC-033), for org admins only.
    ...(page.props.auth.user?.role === 'org_admin'
        ? [
              {
                  title: 'organization.nav',
                  href: organizationSettings(),
                  icon: Building2,
              },
          ]
        : []),
]);
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
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
