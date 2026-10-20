<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/organization/settings';
import type { GradingScale } from '@/types';

/*
 * UC-033: the org admin's organisation settings (DATA-011). The subdomain is
 * shown but cannot be changed.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'organization.settings.title', href: edit() }],
    },
});

type Locale = 'el' | 'en';

const props = defineProps<{
    settings: {
        subdomain: string;
        logo_url: string | null;
        accent_color: string | null;
        student_term_el: string;
        student_term_en: string;
        teacher_term_el: string;
        teacher_term_en: string;
        grading_scale: GradingScale;
        leaderboard_enabled: boolean;
        default_locale: Locale;
    };
    gradingScales: GradingScale[];
    locales: Locale[];
}>();

const form = useForm({
    logo: null as File | null,
    remove_logo: false,
    accent_color: props.settings.accent_color ?? '',
    student_term_el: props.settings.student_term_el,
    student_term_en: props.settings.student_term_en,
    teacher_term_el: props.settings.teacher_term_el,
    teacher_term_en: props.settings.teacher_term_en,
    grading_scale: props.settings.grading_scale,
    leaderboard_enabled: props.settings.leaderboard_enabled,
    default_locale: props.settings.default_locale,
});

const logoInput = ref<HTMLInputElement | null>(null);
const accentIsValid = computed(() => /^#[0-9a-f]{6}$/i.test(form.accent_color));

const termFields = [
    'student_term_el',
    'student_term_en',
    'teacher_term_el',
    'teacher_term_en',
] as const;

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

function chooseLogo(event: Event): void {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (form.logo) {
        form.remove_logo = false;
    }
}

function submit(): void {
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
            form.remove_logo = false;

            if (logoInput.value) {
                logoInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <Head :title="$t('organization.settings.title')" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <div class="space-y-1">
            <h1 class="text-xl font-medium" data-test="organization-title">
                {{ $t('organization.settings.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ $t('organization.settings.description') }}
            </p>
        </div>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="subdomain">{{
                    $t('organization.settings.subdomain')
                }}</Label>
                <Input
                    id="subdomain"
                    :model-value="settings.subdomain"
                    readonly
                    disabled
                    data-test="organization-subdomain"
                />
                <p class="text-sm text-muted-foreground">
                    {{ $t('organization.settings.subdomain_help') }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="logo">{{
                    $t('organization.settings.logo_label')
                }}</Label>
                <div class="flex items-center gap-4">
                    <img
                        v-if="settings.logo_url && !form.remove_logo"
                        :src="settings.logo_url"
                        :alt="$t('organization.settings.logo_alt')"
                        class="h-12 max-w-48 rounded-md border object-contain p-1"
                        data-test="settings-logo"
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('organization.settings.logo_none') }}
                    </p>
                </div>
                <input
                    id="logo"
                    ref="logoInput"
                    type="file"
                    name="logo"
                    accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml"
                    class="text-sm"
                    data-test="logo-input"
                    @change="chooseLogo"
                />
                <p class="text-sm text-muted-foreground">
                    {{ $t('organization.settings.logo_help') }}
                </p>
                <Label
                    v-if="settings.logo_url"
                    for="remove_logo"
                    class="flex items-center gap-3"
                >
                    <Checkbox
                        id="remove_logo"
                        v-model="form.remove_logo"
                        :disabled="form.logo !== null"
                        data-test="remove-logo"
                    />
                    <span>{{ $t('organization.settings.remove_logo') }}</span>
                </Label>
                <InputError :message="form.errors.logo" />
            </div>

            <div class="grid gap-2">
                <Label for="accent_color">{{
                    $t('organization.settings.accent_color_label')
                }}</Label>
                <div class="flex items-center gap-3">
                    <span
                        class="size-9 shrink-0 rounded-md border"
                        :style="
                            accentIsValid
                                ? { backgroundColor: form.accent_color }
                                : {}
                        "
                    />
                    <Input
                        id="accent_color"
                        v-model="form.accent_color"
                        maxlength="7"
                        autocomplete="off"
                        class="max-w-36 font-mono"
                        data-test="accent-color"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="form.accent_color === ''"
                        @click="form.accent_color = ''"
                    >
                        {{ $t('organization.settings.accent_color_clear') }}
                    </Button>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ $t('organization.settings.accent_color_help') }}
                </p>
                <InputError :message="form.errors.accent_color" />
            </div>

            <fieldset class="grid gap-4">
                <legend class="mb-2 font-medium">
                    {{ $t('organization.settings.terminology') }}
                </legend>
                <div
                    v-for="field in termFields"
                    :key="field"
                    class="grid gap-2"
                >
                    <Label :for="field">{{
                        $t(`organization.settings.${field}_label`)
                    }}</Label>
                    <Input
                        :id="field"
                        v-model="form[field]"
                        required
                        maxlength="255"
                    />
                    <InputError :message="form.errors[field]" />
                </div>
            </fieldset>

            <div class="grid gap-2">
                <Label for="grading_scale">{{
                    $t('organization.settings.grading_scale_label')
                }}</Label>
                <select
                    id="grading_scale"
                    v-model="form.grading_scale"
                    :class="selectClass"
                >
                    <option
                        v-for="scale in gradingScales"
                        :key="scale"
                        :value="scale"
                    >
                        {{ $t(`organization.grading_scales.${scale}`) }}
                    </option>
                </select>
                <InputError :message="form.errors.grading_scale" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="leaderboard_enabled"
                    class="flex items-center gap-3"
                >
                    <Checkbox
                        id="leaderboard_enabled"
                        v-model="form.leaderboard_enabled"
                        data-test="leaderboard-enabled"
                    />
                    <span>{{
                        $t('organization.settings.leaderboard_label')
                    }}</span>
                </Label>
                <InputError :message="form.errors.leaderboard_enabled" />
            </div>

            <div class="grid gap-2">
                <Label for="default_locale">{{
                    $t('organization.settings.default_locale_label')
                }}</Label>
                <select
                    id="default_locale"
                    v-model="form.default_locale"
                    :class="selectClass"
                >
                    <option
                        v-for="locale in locales"
                        :key="locale"
                        :value="locale"
                    >
                        {{ $t(`organization.locales.${locale}`) }}
                    </option>
                </select>
                <p class="text-sm text-muted-foreground">
                    {{ $t('organization.settings.default_locale_help') }}
                </p>
                <InputError :message="form.errors.default_locale" />
            </div>

            <div>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="organization-save"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('organization.settings.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
