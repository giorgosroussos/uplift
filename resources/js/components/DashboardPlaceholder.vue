<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';

/*
 * Home page of a role until the real dashboards arrive (PHASE-7).
 */
defineProps<{
    titleKey: string;
}>();
</script>

<template>
    <Head :title="$t(titleKey)" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-xl font-medium" data-test="dashboard-title">
            {{ $t(titleKey) }}
        </h1>
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                v-for="tile in 3"
                :key="tile"
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
