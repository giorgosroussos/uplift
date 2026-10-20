<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn } from '@/lib/utils';
import { edit as passwordSettings } from '@/routes/settings/password';
import { edit as profileSettings } from '@/routes/settings/profile';
import { show as twoFactorSettings } from '@/routes/settings/two-factor';
import type { Auth, NavItem } from '@/types';

/*
 * UC-006: the sections of the user's own settings. Two-factor
 * authentication is offered only to org admins (SEC-012).
 */
const page = usePage<{ auth: Auth }>();
const { isCurrentUrl } = useCurrentUrl();

const items = computed<NavItem[]>(() => [
    { title: 'profile.settings.profile', href: profileSettings() },
    { title: 'profile.settings.password', href: passwordSettings() },
    ...(page.props.auth.user?.role === 'org_admin'
        ? [
              {
                  title: 'profile.settings.two_factor',
                  href: twoFactorSettings(),
              },
          ]
        : []),
]);
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <div class="space-y-1">
            <h1 class="text-xl font-medium">
                {{ $t('profile.settings.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ $t('profile.settings.description') }}
            </p>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-12">
            <nav
                class="flex w-full flex-col gap-1 lg:w-56"
                :aria-label="$t('profile.settings.sections')"
                data-test="settings-nav"
            >
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    :class="
                        cn(
                            'rounded-md px-3 py-2 text-sm hover:bg-muted',
                            isCurrentUrl(item.href) && 'bg-muted font-medium',
                        )
                    "
                >
                    {{ $t(item.title) }}
                </Link>
            </nav>

            <Separator class="lg:hidden" />

            <div class="flex max-w-2xl flex-1 flex-col gap-6">
                <slot />
            </div>
        </div>
    </div>
</template>
