<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { dashboard } from '@/routes/platform';

/*
 * SEC-011: the recovery codes of a platform admin's just confirmed MFA,
 * shown once.
 */
defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'platform.recovery.title',
            description: 'platform.recovery.description',
        },
    ],
});

defineProps<{
    recoveryCodes: string[];
}>();
</script>

<template>
    <Head :title="$t('platform.recovery.title')" />

    <div class="flex flex-col gap-6">
        <ul
            class="grid gap-1 rounded-md bg-muted p-4 text-center font-mono text-sm"
            data-test="platform-recovery-codes"
        >
            <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
        </ul>

        <Button as-child class="w-full">
            <Link :href="dashboard()" data-test="platform-recovery-continue">
                {{ $t('platform.recovery.continue') }}
            </Link>
        </Button>
    </div>
</template>
