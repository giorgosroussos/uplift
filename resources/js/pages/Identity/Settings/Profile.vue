<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/composables/useInitials';
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue';
import { avatarNumber, avatarUrl } from '@/lib/avatars';
import { cn } from '@/lib/utils';
import { edit, update } from '@/routes/settings/profile';
import type { Role } from '@/types';

/*
 * UC-006: the user's own profile (DATA-014). Email, role, date of birth and
 * minor flag are shown but cannot be changed.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'profile.settings.title', href: edit() }],
    },
});

type Locale = 'el' | 'en';

const props = defineProps<{
    profile: {
        name: string;
        nickname: string;
        avatar: string | null;
        locale: Locale | null;
        email: string;
        role: Role;
        date_of_birth: string | null;
        is_minor: boolean | null;
    };
    avatars: string[];
    locales: Locale[];
    organizationLocale: Locale;
    currentLocale: Locale;
}>();

const form = useForm({
    name: props.profile.name,
    nickname: props.profile.nickname,
    avatar: props.profile.avatar ?? '',
    locale: props.profile.locale ?? '',
});

const { getInitials } = useInitials();

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

const avatarClass = (selected: boolean): string =>
    cn(
        'flex size-12 items-center justify-center overflow-hidden rounded-lg border-2 text-sm font-medium',
        selected
            ? 'border-primary ring-2 ring-primary/40'
            : 'border-transparent hover:border-muted-foreground/40',
    );

// A new language applies to the whole UI right after saving.
watch(
    () => props.currentLocale,
    (locale) => {
        document.documentElement.lang = locale;
        void loadLanguageAsync(locale);
    },
);

function submit(): void {
    form.patch(update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('profile.profile.title')" />

    <SettingsLayout>
        <section class="space-y-1">
            <h2 class="text-lg font-medium">
                {{ $t('profile.profile.title') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ $t('profile.profile.description') }}
            </p>
        </section>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">{{ $t('profile.profile.name') }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    maxlength="255"
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="nickname">{{
                    $t('profile.profile.nickname')
                }}</Label>
                <Input
                    id="nickname"
                    v-model="form.nickname"
                    required
                    maxlength="30"
                    autocomplete="nickname"
                    data-test="nickname"
                />
                <p class="text-sm text-muted-foreground">
                    {{ $t('profile.profile.nickname_help') }}
                </p>
                <InputError
                    :message="form.errors.nickname"
                    data-test="nickname-error"
                />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ $t('profile.profile.avatar') }}
                </legend>
                <div
                    class="flex flex-wrap gap-2"
                    role="radiogroup"
                    :aria-label="$t('profile.profile.avatar')"
                >
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="form.avatar === ''"
                        :aria-label="$t('profile.profile.avatar_none')"
                        :class="avatarClass(form.avatar === '')"
                        class="bg-muted"
                        data-test="avatar-none"
                        @click="form.avatar = ''"
                    >
                        {{ getInitials(form.name) }}
                    </button>
                    <button
                        v-for="key in avatars"
                        :key="key"
                        type="button"
                        role="radio"
                        :aria-checked="form.avatar === key"
                        :aria-label="
                            $t('profile.profile.avatar_option', {
                                number: String(avatarNumber(key)),
                            })
                        "
                        :class="avatarClass(form.avatar === key)"
                        :data-test="`avatar-${key}`"
                        @click="form.avatar = key"
                    >
                        <img
                            :src="avatarUrl(key) ?? undefined"
                            alt=""
                            class="size-full"
                        />
                    </button>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ $t('profile.profile.avatar_help') }}
                </p>
                <InputError :message="form.errors.avatar" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="locale">{{ $t('profile.profile.language') }}</Label>
                <select
                    id="locale"
                    v-model="form.locale"
                    :class="selectClass"
                    data-test="locale"
                >
                    <option value="">
                        {{
                            $t('profile.profile.language_default', {
                                language: $t(
                                    `organization.locales.${organizationLocale}`,
                                ),
                            })
                        }}
                    </option>
                    <option
                        v-for="locale in locales"
                        :key="locale"
                        :value="locale"
                    >
                        {{ $t(`organization.locales.${locale}`) }}
                    </option>
                </select>
                <InputError :message="form.errors.locale" />
            </div>

            <div>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="profile-save"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('profile.profile.save') }}
                </Button>
            </div>
        </form>

        <section class="space-y-4 border-t pt-6" data-test="account-details">
            <div class="space-y-1">
                <h2 class="text-lg font-medium">
                    {{ $t('profile.profile.account') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ $t('profile.profile.account_help') }}
                </p>
            </div>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">
                        {{ $t('profile.profile.email') }}
                    </dt>
                    <dd class="break-all">{{ profile.email }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ $t('profile.profile.role') }}
                    </dt>
                    <dd>{{ $t(`users.roles.${profile.role}`) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ $t('profile.profile.date_of_birth') }}
                    </dt>
                    <dd>
                        {{
                            profile.date_of_birth ??
                            $t('profile.profile.not_set')
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ $t('profile.profile.is_minor') }}
                    </dt>
                    <dd>
                        {{
                            profile.is_minor === null
                                ? $t('profile.profile.not_set')
                                : profile.is_minor
                                  ? $t('profile.profile.yes')
                                  : $t('profile.profile.no')
                        }}
                    </dd>
                </div>
            </dl>
        </section>
    </SettingsLayout>
</template>
