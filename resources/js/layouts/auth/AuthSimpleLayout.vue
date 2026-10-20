<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

// DATA-011: the organisation's logo in the header, else the app logo.
const page = usePage();
const organization = computed(() => page.props.organization);
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <img
                            v-if="organization?.logo_url"
                            :src="organization.logo_url"
                            :alt="organization.name"
                            class="mb-1 h-12 max-w-48 object-contain"
                            data-test="organization-logo"
                        />
                        <div
                            v-else
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                            data-test="app-logo"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                        <span class="sr-only">{{ $t(title ?? '') }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">
                            {{ $t(title ?? '') }}
                        </h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ $t(description ?? '') }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
