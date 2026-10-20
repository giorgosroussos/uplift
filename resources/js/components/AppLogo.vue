<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const name = computed(() => page.props.name);
// DATA-011: the organisation's logo when it has one, else the app logo.
const organization = computed(() => page.props.organization);
</script>

<template>
    <img
        v-if="organization?.logo_url"
        :src="organization.logo_url"
        :alt="organization.name"
        class="size-8 rounded-md object-contain"
        data-test="organization-logo"
    />
    <div
        v-else
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        data-test="app-logo"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
