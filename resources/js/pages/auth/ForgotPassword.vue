<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'ui.auth.forgot_password.title',
        description: 'ui.auth.forgot_password.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="$t('ui.auth.forgot_password.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-slot="{ errors, processing }" v-bind="email.form()">
            <div class="grid gap-2">
                <Label for="email">{{ $t('ui.auth.email') }}</Label>
                <Input
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    autocomplete="off"
                    :placeholder="$t('ui.auth.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('ui.auth.forgot_password.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ $t('ui.auth.forgot_password.return_to') }}</span>
            <TextLink :href="login()">{{
                $t('ui.auth.forgot_password.log_in')
            }}</TextLink>
        </div>
    </div>
</template>
