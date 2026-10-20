<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, subdomain as subdomainCheck } from '@/routes/register';

defineOptions({
    layout: {
        title: 'ui.auth.register.title',
        description: 'ui.auth.register.description',
    },
});

defineProps<{
    domain: string;
    passwordRules: string;
}>();

type Availability =
    | { state: 'idle' }
    | { state: 'checking' }
    | { state: 'available' }
    | { state: 'unavailable'; message: string }
    | { state: 'failed' };

const form = useForm({
    organization_name: '',
    subdomain: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const availability = ref<Availability>({ state: 'idle' });
const tooMany = ref(false);
let latestCheck = 0;

const checkAvailability = useDebounceFn(async (value: string) => {
    const check = ++latestCheck;

    try {
        const response = await fetch(
            subdomainCheck({ query: { subdomain: value } }).url,
            { headers: { Accept: 'application/json' } },
        );

        if (check !== latestCheck) {
            return;
        }

        if (response.ok) {
            availability.value = { state: 'available' };

            return;
        }

        const problem = (await response.json()) as { detail?: string };

        availability.value =
            response.status === 422 && problem.detail
                ? { state: 'unavailable', message: problem.detail }
                : { state: 'failed' };
    } catch {
        if (check === latestCheck) {
            availability.value = { state: 'failed' };
        }
    }
}, 400);

watch(
    () => form.subdomain,
    (value) => {
        form.clearErrors('subdomain');

        if (value.trim() === '') {
            latestCheck++;
            availability.value = { state: 'idle' };

            return;
        }

        availability.value = { state: 'checking' };
        void checkAvailability(value);
    },
);

function submit(): void {
    tooMany.value = false;

    form.post(store().url, {
        onHttpException: (response) => {
            if (response.status === 429) {
                tooMany.value = true;

                return false;
            }
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="$t('ui.auth.register.page_title')" />

    <div
        v-if="tooMany"
        role="alert"
        class="text-center text-sm font-medium text-red-600"
        data-test="register-too-many"
    >
        {{ $t('ui.auth.register.too_many') }}
    </div>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <fieldset class="grid gap-6">
            <legend class="mb-4 text-sm font-semibold">
                {{ $t('ui.auth.register.organization_section') }}
            </legend>

            <div class="grid gap-2">
                <Label for="organization_name">{{
                    $t('ui.auth.register.organization_name')
                }}</Label>
                <Input
                    id="organization_name"
                    v-model="form.organization_name"
                    v-focus
                    name="organization_name"
                    required
                    autocomplete="organization"
                    :placeholder="
                        $t('ui.auth.register.organization_name_placeholder')
                    "
                />
                <InputError :message="form.errors.organization_name" />
            </div>

            <div class="grid gap-2">
                <Label for="subdomain">{{
                    $t('ui.auth.register.subdomain')
                }}</Label>
                <div class="flex items-center gap-2">
                    <Input
                        id="subdomain"
                        v-model="form.subdomain"
                        name="subdomain"
                        required
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        aria-describedby="subdomain-hint subdomain-status"
                        :placeholder="
                            $t('ui.auth.register.subdomain_placeholder')
                        "
                    />
                    <span
                        class="text-sm whitespace-nowrap text-muted-foreground"
                        >.{{ domain }}</span
                    >
                </div>
                <p id="subdomain-hint" class="text-xs text-muted-foreground">
                    {{ $t('ui.auth.register.subdomain_hint') }}
                </p>
                <div id="subdomain-status" aria-live="polite">
                    <InputError
                        v-if="form.errors.subdomain"
                        :message="form.errors.subdomain"
                    />
                    <p
                        v-else-if="availability.state === 'checking'"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('ui.auth.register.subdomain_checking') }}
                    </p>
                    <p
                        v-else-if="availability.state === 'available'"
                        class="text-sm text-green-600"
                        data-test="subdomain-available"
                    >
                        {{ $t('ui.auth.register.subdomain_available') }}
                    </p>
                    <InputError
                        v-else-if="availability.state === 'unavailable'"
                        data-test="subdomain-unavailable"
                        :message="availability.message"
                    />
                    <p
                        v-else-if="availability.state === 'failed'"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('ui.auth.register.subdomain_check_failed') }}
                    </p>
                </div>
            </div>
        </fieldset>

        <fieldset class="grid gap-6">
            <legend class="mb-4 text-sm font-semibold">
                {{ $t('ui.auth.register.admin_section') }}
            </legend>

            <div class="grid gap-2">
                <Label for="name">{{
                    $t('ui.auth.register.admin_name')
                }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    name="name"
                    required
                    autocomplete="name"
                    :placeholder="$t('ui.auth.register.admin_name_placeholder')"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('ui.auth.email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    :placeholder="$t('ui.auth.email_placeholder')"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('ui.auth.password') }}</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :placeholder="$t('ui.auth.password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('ui.auth.password_confirmation')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :placeholder="$t('ui.auth.password_confirmation')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>
        </fieldset>

        <Button
            type="submit"
            class="mt-2 w-full"
            :disabled="form.processing"
            data-test="register-button"
        >
            <Spinner v-if="form.processing" />
            {{ $t('ui.auth.register.submit') }}
        </Button>
    </form>
</template>
