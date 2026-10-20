<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head :title="$t('ui.welcome.title')" />

    <div
        class="flex min-h-svh flex-col items-center bg-background p-6 text-foreground lg:p-8"
    >
        <header class="w-full max-w-4xl">
            <nav
                class="flex items-center justify-end gap-4"
                :aria-label="$t('ui.nav.main')"
            >
                <Button v-if="$page.props.auth.user" as-child variant="outline">
                    <Link :href="dashboard()">{{
                        $t('ui.nav.dashboard')
                    }}</Link>
                </Button>
                <template v-else>
                    <Button as-child variant="ghost">
                        <Link :href="login()" data-test="welcome-login">{{
                            $t('ui.nav.log_in')
                        }}</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="register()" data-test="welcome-register">{{
                            $t('ui.nav.register')
                        }}</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="flex w-full max-w-4xl flex-1 flex-col items-center justify-center gap-6 text-center"
        >
            <AppLogoIcon class="size-16 fill-current" />
            <h1
                class="text-3xl font-semibold tracking-tight"
                data-test="welcome-heading"
            >
                {{ $t('ui.welcome.heading') }}
            </h1>
            <p class="max-w-xl text-muted-foreground">
                {{ $t('ui.welcome.lead') }}
            </p>
        </main>
    </div>
</template>
