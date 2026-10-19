<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'ui.auth.verify_email.title',
        description: 'ui.auth.verify_email.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="$t('ui.auth.verify_email.title')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ $t('ui.auth.verify_email.link_sent') }}
    </div>

    <Form
        v-slot="{ processing }"
        v-bind="send.form()"
        class="space-y-6 text-center"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ $t('ui.auth.verify_email.resend') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ $t('ui.nav.log_out') }}
        </TextLink>
    </Form>
</template>
