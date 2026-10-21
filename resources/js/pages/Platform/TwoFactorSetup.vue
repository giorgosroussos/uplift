<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/platform/two-factor/setup';

/*
 * SEC-011: MFA is required for platform admins. An admin without it sets it
 * up here, after the password step and before entering the platform.
 */
defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'platform.setup.title',
            description: 'platform.setup.description',
        },
    ],
});

defineProps<{
    /** The QR code as an SVG data URI. */
    qrCode: string;
    setupKey: string;
}>();

const form = useForm({ code: '' });

function submit(): void {
    form.post(store.url(), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="$t('platform.setup.title')" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <p class="text-center text-sm text-muted-foreground">
            {{ $t('platform.setup.scan') }}
        </p>

        <img
            :src="qrCode"
            :alt="$t('platform.setup.qr_code')"
            class="mx-auto size-48 rounded-md bg-white p-2"
            data-test="platform-setup-qr"
        />

        <div class="space-y-1 text-center text-sm">
            <p class="text-muted-foreground">
                {{ $t('platform.setup.key') }}
            </p>
            <code class="font-mono break-all" data-test="platform-setup-key">{{
                setupKey
            }}</code>
        </div>

        <div class="grid justify-center gap-2">
            <Label for="code" class="text-center">{{
                $t('platform.setup.code')
            }}</Label>
            <InputOTP
                id="code"
                v-model="form.code"
                :maxlength="6"
                inputmode="numeric"
                autocomplete="one-time-code"
                data-test="platform-setup-code"
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

        <Button
            type="submit"
            class="w-full"
            :disabled="form.processing || form.code.length !== 6"
            data-test="platform-setup-submit"
        >
            <Spinner v-if="form.processing" />
            {{ $t('platform.setup.submit') }}
        </Button>
    </form>
</template>
