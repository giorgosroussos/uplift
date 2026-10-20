<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/two-factor/login';

/*
 * SEC-012: the login step of an org admin with MFA, with a TOTP code or a
 * recovery code. Limited to 5 attempts per minute per login session.
 */
defineOptions({
    layout: {
        title: 'profile.challenge.title',
        description: 'profile.challenge.description',
    },
});

const useRecovery = ref(false);

const form = useForm({ code: '', recovery_code: '' });

function toggle(): void {
    useRecovery.value = !useRecovery.value;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    form.transform((data) =>
        useRecovery.value
            ? { recovery_code: data.recovery_code }
            : { code: data.code },
    ).post(store.url(), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="$t('profile.challenge.title')" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <p v-if="useRecovery" class="text-center text-sm text-muted-foreground">
            {{ $t('profile.challenge.recovery_description') }}
        </p>

        <div v-if="!useRecovery" class="grid justify-center gap-2">
            <Label for="code" class="sr-only">{{
                $t('profile.challenge.code')
            }}</Label>
            <InputOTP
                id="code"
                v-model="form.code"
                :maxlength="6"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                data-test="two-factor-challenge-code"
            >
                <InputOTPGroup>
                    <InputOTPSlot
                        v-for="index in 6"
                        :key="index"
                        :index="index - 1"
                    />
                </InputOTPGroup>
            </InputOTP>
            <InputError :message="form.errors.code" />
        </div>

        <div v-else class="grid gap-2">
            <Label for="recovery_code">{{
                $t('profile.challenge.recovery_code')
            }}</Label>
            <Input
                id="recovery_code"
                v-model="form.recovery_code"
                autocomplete="one-time-code"
                autofocus
                required
            />
            <InputError :message="form.errors.recovery_code" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="form.processing"
            data-test="two-factor-challenge-submit"
        >
            <Spinner v-if="form.processing" />
            {{ $t('profile.challenge.submit') }}
        </Button>

        <button
            type="button"
            class="text-center text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
            @click="toggle"
        >
            {{
                useRecovery
                    ? $t('profile.challenge.use_code')
                    : $t('profile.challenge.use_recovery')
            }}
        </button>
    </form>
</template>
