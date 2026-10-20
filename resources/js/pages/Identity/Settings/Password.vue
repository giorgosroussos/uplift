<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue';
import { edit as profileSettings } from '@/routes/settings/profile';
import { update } from '@/routes/settings/password';

/*
 * UC-006 step 2: change the password with the current one; the user's other
 * sessions end (DATA-014, SEC-010).
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'profile.settings.title', href: profileSettings() },
        ],
    },
});

defineProps<{
    passwordRules: string;
}>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.put(update.url(), {
        preserveScroll: true,
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="$t('profile.password.title')" />

    <SettingsLayout>
        <section class="space-y-1">
            <h2 class="text-lg font-medium">
                {{ $t('profile.password.title') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ $t('profile.password.description') }}
            </p>
        </section>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="current_password">{{
                    $t('profile.password.current')
                }}</Label>
                <PasswordInput
                    id="current_password"
                    v-model="form.current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('profile.password.new') }}</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('profile.password.confirm')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <div>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="password-save"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('profile.password.save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
