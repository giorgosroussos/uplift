<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ShieldCheck, ShieldOff } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue';
import { edit as profileSettings } from '@/routes/settings/profile';
import {
    confirm,
    disable,
    enable,
    regenerateRecoveryCodes,
} from '@/routes/two-factor';

/*
 * UC-006 step 3: an org admin's optional TOTP MFA (SEC-012). Turning it on
 * needs the password (asked before this page) and a valid first code.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'profile.settings.title', href: profileSettings() },
        ],
    },
});

defineProps<{
    enabled: boolean;
    pending: boolean;
    /** The QR code as an SVG data URI. */
    qrCode: string | null;
    setupKey: string | null;
    recoveryCodes?: string[];
}>();

const busy = ref(false);
const showRecoveryCodes = ref(false);

const confirmForm = useForm({ code: '' });

function visit(
    method: 'post' | 'delete',
    url: string,
    reloadCodes = false,
): void {
    router.visit(url, {
        method,
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
        onSuccess: () => {
            if (reloadCodes) {
                router.reload({ only: ['recoveryCodes'] });
            }
        },
    });
}

function turnOn(): void {
    visit('post', enable.url());
}

function turnOff(): void {
    showRecoveryCodes.value = false;
    visit('delete', disable.url());
}

function confirmCode(): void {
    confirmForm.post(confirm.url(), {
        preserveScroll: true,
        onSuccess: () => {
            confirmForm.reset();
            showRecoveryCodes.value = true;
            router.reload({ only: ['recoveryCodes'] });
        },
    });
}

function toggleRecoveryCodes(): void {
    showRecoveryCodes.value = !showRecoveryCodes.value;

    if (showRecoveryCodes.value) {
        router.reload({ only: ['recoveryCodes'] });
    }
}

function regenerate(): void {
    visit('post', regenerateRecoveryCodes.url(), true);
}
</script>

<template>
    <Head :title="$t('profile.two_factor.title')" />

    <SettingsLayout>
        <section class="space-y-1">
            <h2 class="text-lg font-medium">
                {{ $t('profile.two_factor.title') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ $t('profile.two_factor.description') }}
            </p>
        </section>

        <p
            class="flex items-center gap-2 text-sm font-medium"
            data-test="two-factor-status"
        >
            <ShieldCheck v-if="enabled" class="size-4 text-green-600" />
            <ShieldOff v-else class="size-4 text-muted-foreground" />
            {{
                enabled
                    ? $t('profile.two_factor.status_enabled')
                    : $t('profile.two_factor.status_disabled')
            }}
        </p>

        <div v-if="!enabled && !pending">
            <Button
                :disabled="busy"
                data-test="two-factor-enable"
                @click="turnOn"
            >
                <Spinner v-if="busy" />
                {{ $t('profile.two_factor.enable') }}
            </Button>
        </div>

        <section
            v-if="pending"
            class="space-y-4 rounded-lg border p-4"
            data-test="two-factor-setup"
        >
            <h3 class="font-medium">
                {{ $t('profile.two_factor.setup_title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ $t('profile.two_factor.setup_scan') }}
            </p>
            <img
                v-if="qrCode"
                :src="qrCode"
                :alt="$t('profile.two_factor.qr_code')"
                class="size-48 rounded-md bg-white p-2"
                data-test="two-factor-qr"
            />
            <div v-if="setupKey" class="space-y-1 text-sm">
                <p class="text-muted-foreground">
                    {{ $t('profile.two_factor.setup_key') }}
                </p>
                <code class="font-mono break-all" data-test="two-factor-key">{{
                    setupKey
                }}</code>
            </div>
            <form class="space-y-3" @submit.prevent="confirmCode">
                <Label for="code">{{ $t('profile.two_factor.code') }}</Label>
                <InputOTP
                    id="code"
                    v-model="confirmForm.code"
                    :maxlength="6"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    data-test="two-factor-code"
                >
                    <InputOTPGroup>
                        <InputOTPSlot
                            v-for="index in 6"
                            :key="index"
                            :index="index - 1"
                        />
                    </InputOTPGroup>
                </InputOTP>
                <InputError :message="confirmForm.errors.code" />
                <div class="flex gap-2">
                    <Button
                        type="submit"
                        :disabled="
                            confirmForm.processing ||
                            confirmForm.code.length !== 6
                        "
                        data-test="two-factor-confirm"
                    >
                        <Spinner v-if="confirmForm.processing" />
                        {{ $t('profile.two_factor.confirm') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        @click="turnOff"
                    >
                        {{ $t('profile.two_factor.cancel') }}
                    </Button>
                </div>
            </form>
        </section>

        <template v-if="enabled">
            <section class="space-y-3" data-test="two-factor-recovery">
                <h3 class="font-medium">
                    {{ $t('profile.two_factor.recovery_title') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ $t('profile.two_factor.recovery_help') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        data-test="two-factor-recovery-toggle"
                        @click="toggleRecoveryCodes"
                    >
                        {{
                            showRecoveryCodes
                                ? $t('profile.two_factor.recovery_hide')
                                : $t('profile.two_factor.recovery_show')
                        }}
                    </Button>
                    <Button
                        v-if="showRecoveryCodes"
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        @click="regenerate"
                    >
                        {{ $t('profile.two_factor.recovery_regenerate') }}
                    </Button>
                </div>
                <ul
                    v-if="showRecoveryCodes && recoveryCodes"
                    class="grid gap-1 rounded-md bg-muted p-4 font-mono text-sm"
                    data-test="two-factor-recovery-codes"
                >
                    <li v-for="code in recoveryCodes" :key="code">
                        {{ code }}
                    </li>
                </ul>
            </section>

            <div>
                <Button
                    variant="destructive"
                    :disabled="busy"
                    data-test="two-factor-disable"
                    @click="turnOff"
                >
                    <Spinner v-if="busy" />
                    {{ $t('profile.two_factor.disable') }}
                </Button>
            </div>
        </template>
    </SettingsLayout>
</template>
