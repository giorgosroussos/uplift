<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { index } from '@/routes/notifications';

/*
 * DEC-002: the bell in the app header with the unread count, refreshed on
 * every page load (shared prop notifications.unread_count).
 */
const page = usePage();
const unread = computed(() => page.props.notifications?.unread_count ?? 0);
</script>

<template>
    <Link
        v-if="page.props.notifications"
        :href="index()"
        class="relative inline-flex size-9 items-center justify-center rounded-md hover:bg-accent hover:text-accent-foreground"
        :aria-label="
            unread > 0
                ? `${$t('notifications.bell.label')}, ${$t('notifications.bell.unread', { count: String(unread) })}`
                : $t('notifications.bell.label')
        "
        data-test="notification-bell"
    >
        <Bell class="size-5" />
        <span
            v-if="unread > 0"
            class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-medium text-primary-foreground"
            data-test="notification-count"
        >
            {{ unread > 99 ? '99+' : unread }}
        </span>
    </Link>
</template>
