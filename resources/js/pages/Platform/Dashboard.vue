<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes/platform';

/*
 * Landing page of a logged-in platform admin (SEC-006). The platform
 * dashboard and organisation management (UC-035, UC-036) come later.
 */
defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'platform.dashboard.title',
            description: 'platform.dashboard.description',
        },
    ],
});

defineProps<{
    admin: { name: string | null };
}>();
</script>

<template>
    <Head :title="$t('platform.dashboard.title')" />

    <div class="flex flex-col gap-6">
        <p class="text-center text-sm" data-test="platform-admin-name">
            {{
                $t('platform.dashboard.signed_in_as', {
                    name: admin.name ?? '',
                })
            }}
        </p>

        <Button as-child variant="outline" class="w-full">
            <Link :href="logout()" as="button" data-test="platform-logout">
                {{ $t('platform.dashboard.log_out') }}
            </Link>
        </Button>
    </div>
</template>
