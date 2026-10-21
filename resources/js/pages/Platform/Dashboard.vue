<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { index as organizationsIndex } from '@/routes/platform/organizations';

/*
 * UC-035: the platform dashboard of a platform admin (SEC-006): key metrics
 * across all organisations and the newest organisations.
 */
defineOptions({ layout: PlatformLayout });

type Status = 'active' | 'suspended';

defineProps<{
    admin: { name: string | null };
    metrics: {
        organizations: { total: number; active: number; suspended: number };
        users: {
            total: number;
            active: number;
            student: number;
            teacher: number;
            org_admin: number;
        };
        recent_organizations: {
            id: string;
            name: string;
            subdomain: string;
            status: Status;
            created_at: string | null;
        }[];
    };
}>();

function formatDate(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleDateString(document.documentElement.lang)
        : '';
}
</script>

<template>
    <Head :title="$t('platform.dashboard.title')" />

    <div class="flex flex-col gap-8">
        <div class="space-y-1">
            <h1 class="text-xl font-medium">
                {{ $t('platform.dashboard.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ $t('platform.dashboard.description') }}
            </p>
            <p class="text-sm" data-test="platform-admin-name">
                {{
                    $t('platform.dashboard.signed_in_as', {
                        name: admin.name ?? '',
                    })
                }}
            </p>
        </div>

        <section class="space-y-3">
            <h2 class="font-medium">
                {{ $t('platform.dashboard.organizations') }}
            </h2>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div
                    v-for="key in ['total', 'active', 'suspended'] as const"
                    :key="key"
                    class="rounded-xl border p-4"
                    :data-test="`metric-organizations-${key}`"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ $t(`platform.dashboard.${key}`) }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ metrics.organizations[key] }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="space-y-3">
            <h2 class="font-medium">{{ $t('platform.dashboard.users') }}</h2>
            <dl class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div
                    class="rounded-xl border p-4"
                    data-test="metric-users-total"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ $t('platform.dashboard.total') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ metrics.users.total }}
                    </dd>
                </div>
                <div
                    class="rounded-xl border p-4"
                    data-test="metric-users-active"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ $t('platform.dashboard.active_users') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ metrics.users.active }}
                    </dd>
                </div>
                <div
                    v-for="role in ['student', 'teacher', 'org_admin'] as const"
                    :key="role"
                    class="rounded-xl border p-4"
                    :data-test="`metric-users-${role}`"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ $t(`platform.roles.${role}`) }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ metrics.users[role] }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-medium">
                    {{ $t('platform.dashboard.recent') }}
                </h2>
                <Button as-child variant="outline" size="sm">
                    <Link
                        :href="organizationsIndex()"
                        data-test="manage-organizations"
                    >
                        {{ $t('platform.dashboard.manage') }}
                    </Link>
                </Button>
            </div>
            <p
                v-if="metrics.recent_organizations.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('platform.dashboard.recent_empty') }}
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="organization in metrics.recent_organizations"
                    :key="organization.id"
                    class="flex flex-wrap items-center gap-3 p-3 text-sm"
                    data-test="recent-organization"
                >
                    <span class="flex-1 font-medium">{{
                        organization.name
                    }}</span>
                    <span class="text-muted-foreground">{{
                        organization.subdomain
                    }}</span>
                    <Badge
                        :variant="
                            organization.status === 'active'
                                ? 'secondary'
                                : 'destructive'
                        "
                        >{{
                            $t(`platform.statuses.${organization.status}`)
                        }}</Badge
                    >
                    <span class="text-muted-foreground">{{
                        formatDate(organization.created_at)
                    }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
