<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/platform/login';

/*
 * SEC-006: the password step of a platform admin's login, on the apex
 * domain. It leads to the TOTP step (SEC-011).
 */
defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'platform.login.title',
            description: 'platform.login.description',
        },
    ],
});
</script>

<template>
    <Head :title="$t('platform.login.page_title')" />

    <Form
        v-slot="{ errors, processing }"
        v-bind="store.form()"
        :reset-on-success="['password']"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ $t('ui.auth.email') }}</Label>
                <Input
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    :placeholder="$t('ui.auth.email_placeholder')"
                    data-test="platform-email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('ui.auth.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    :placeholder="$t('ui.auth.password')"
                    data-test="platform-password"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="platform-login-button"
            >
                <Spinner v-if="processing" />
                {{ $t('platform.login.submit') }}
            </Button>
        </div>
    </Form>
</template>
