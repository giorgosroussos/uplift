<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accept } from '@/routes/invitation';
import type { Role } from '@/types';

/*
 * UC-005: the invitee sees the organisation and the assigned role, and sets
 * name and password to activate the account.
 */
defineOptions({
    layout: {
        title: 'invitations.accept.page_title',
    },
});

const props = defineProps<{
    invitation: {
        id: string;
        email: string;
        role: Role;
        organization: string;
    };
    token: string;
    passwordRules: string;
}>();

const action = accept.form({
    invitation: props.invitation.id,
    token: props.token,
});
</script>

<template>
    <Head :title="$t('invitations.accept.page_title')" />

    <div class="space-y-2 text-center" data-test="invitation-summary">
        <p class="text-lg font-semibold">
            {{
                $t('invitations.accept.title', {
                    organization: invitation.organization,
                })
            }}
        </p>
        <p class="text-sm text-muted-foreground">
            {{
                $t('invitations.accept.description', {
                    role: $t(`users.roles.${invitation.role}`),
                })
            }}
        </p>
    </div>

    <Form
        v-slot="{ errors, processing }"
        v-bind="action"
        :reset-on-success="['password', 'password_confirmation']"
        class="flex flex-col gap-6"
    >
        <InputError :message="errors.invitation" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ $t('ui.auth.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    :model-value="invitation.email"
                    autocomplete="email"
                    readonly
                />
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ $t('invitations.accept.name') }}</Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="$t('invitations.accept.name_placeholder')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('ui.auth.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :placeholder="$t('ui.auth.password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('ui.auth.password_confirmation')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :placeholder="$t('ui.auth.password_confirmation')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="accept-invitation-button"
            >
                <Spinner v-if="processing" />
                {{ $t('invitations.accept.submit') }}
            </Button>
        </div>
    </Form>
</template>
