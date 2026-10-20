<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'ui.auth.login.title',
        description: 'ui.auth.login.description',
    },
});

defineProps<{
    status?: string;
    verificationSent: boolean;
    canResetPassword: boolean;
    organization: { name: string } | null;
    identityProviders: string[];
    identityProvidersUnavailable: boolean;
}>();
</script>

<template>
    <Head :title="$t('ui.auth.login.page_title')" />

    <p
        v-if="organization"
        class="text-center text-lg font-semibold"
        data-test="organization-name"
    >
        {{ organization.name }}
    </p>

    <div
        v-if="identityProvidersUnavailable"
        role="alert"
        class="text-center text-sm font-medium text-red-600"
        data-test="identity-providers-unavailable"
    >
        {{ $t('ui.auth.login.providers_unavailable') }}
    </div>

    <div
        v-if="verificationSent"
        role="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
        data-test="verification-sent"
    >
        {{ $t('ui.auth.login.verification_sent') }}
    </div>

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

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
                    :tabindex="1"
                    autocomplete="email"
                    :placeholder="$t('ui.auth.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ $t('ui.auth.password') }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        {{ $t('ui.auth.login.forgot_password') }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="$t('ui.auth.password')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ $t('ui.auth.login.remember_me') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ $t('ui.auth.login.submit') }}
            </Button>
        </div>
    </Form>
</template>
