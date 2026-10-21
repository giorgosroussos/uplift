<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, logout } from '@/routes/platform';
import { index as organizations } from '@/routes/platform/organizations';

/*
 * Shell of the platform administration on the apex domain (SEC-006): the
 * platform admin's navigation (UC-035, UC-036) and logout. Platform admins
 * are not organisation users, so the organisation's app shell is not used.
 */
const page = usePage();

const links = [
    {
        title: 'platform.nav.dashboard',
        href: dashboard(),
        test: 'nav-dashboard',
    },
    {
        title: 'platform.nav.organizations',
        href: organizations(),
        test: 'nav-organizations',
    },
];

function isCurrent(url: string): boolean {
    const path = page.url.split('?')[0];

    return path === url;
}
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <header class="border-b">
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-4 px-4 py-3"
            >
                <Link
                    :href="dashboard()"
                    class="flex items-center gap-2 font-medium"
                >
                    <AppLogoIcon
                        class="size-7 fill-current text-[var(--foreground)] dark:text-white"
                    />
                    <span>{{ $t('platform.dashboard.title') }}</span>
                </Link>
                <nav class="flex flex-1 flex-wrap items-center gap-1">
                    <Button
                        v-for="link in links"
                        :key="link.test"
                        as-child
                        size="sm"
                        :variant="
                            isCurrent(link.href.url) ? 'secondary' : 'ghost'
                        "
                    >
                        <Link :href="link.href" :data-test="link.test">
                            {{ $t(link.title) }}
                        </Link>
                    </Button>
                </nav>
                <Button as-child variant="outline" size="sm">
                    <Link
                        :href="logout()"
                        as="button"
                        data-test="platform-logout"
                    >
                        {{ $t('platform.dashboard.log_out') }}
                    </Link>
                </Button>
            </div>
        </header>
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
