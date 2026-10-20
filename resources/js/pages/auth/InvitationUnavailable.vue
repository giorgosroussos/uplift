<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

/*
 * UC-005 exception: an expired, revoked or already accepted invitation.
 */
defineOptions({
    layout: {
        title: 'invitations.unavailable.title',
    },
});

defineProps<{
    reason: 'expired' | 'accepted' | 'revoked';
}>();
</script>

<template>
    <Head :title="$t('invitations.unavailable.title')" />

    <div class="space-y-4 text-center" data-test="invitation-unavailable">
        <p class="font-medium">
            {{ $t(`invitations.unavailable.${reason}`) }}
        </p>
        <p class="text-sm text-muted-foreground">
            {{ $t('invitations.unavailable.contact_admin') }}
        </p>
        <TextLink :href="login()" class="text-sm">
            {{ $t('invitations.unavailable.log_in') }}
        </TextLink>
    </div>
</template>
