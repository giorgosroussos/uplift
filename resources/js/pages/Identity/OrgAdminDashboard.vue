<script setup lang="ts">
import DashboardPlaceholder from '@/components/DashboardPlaceholder.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'ui.dashboard.title', href: dashboard() }],
    },
});
</script>

<template>
    <DashboardPlaceholder title-key="ui.dashboard.org_admin" />
</template>
