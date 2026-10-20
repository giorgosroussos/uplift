<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/notifications/settings';

/*
 * DEC-002: the user's one switch for DEC-001 notification emails. Account
 * emails and in-app notifications cannot be turned off.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'notifications.settings.title', href: edit() }],
    },
});

const props = defineProps<{
    emailEnabled: boolean;
}>();

const form = useForm({
    email_enabled: props.emailEnabled,
});

function submit(): void {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('notifications.settings.title')" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">
            {{ $t('notifications.settings.heading') }}
        </h1>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email_enabled" class="flex items-center gap-3">
                    <Checkbox
                        id="email_enabled"
                        v-model="form.email_enabled"
                        data-test="email-enabled"
                    />
                    <span>{{ $t('notifications.settings.email_label') }}</span>
                </Label>
                <p class="text-sm text-muted-foreground">
                    {{ $t('notifications.settings.email_help') }}
                </p>
                <InputError :message="form.errors.email_enabled" />
            </div>

            <div>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-notification-settings"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('notifications.settings.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
