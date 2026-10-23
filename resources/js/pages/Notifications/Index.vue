<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { groupByDay } from '@/lib/notificationGroups';
import { notificationMessage } from '@/lib/notificationMessage';
import { index, open, read, readAll, unread } from '@/routes/notifications';
import { edit as settings } from '@/routes/notifications/settings';
import type { Paginated } from '@/types/courses';
import type { NotificationEntry } from '@/types/notifications';

/*
 * UC-007 / DEC-002: the user's notifications, newest first, grouped by day,
 * 20 per page. Opening one marks it read and goes to its target.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'notifications.centre.title', href: index() }],
    },
});

const props = defineProps<{
    entries: Paginated<NotificationEntry>;
}>();

const groups = computed(() => groupByDay(props.entries.data));
const hasUnread = computed(() =>
    props.entries.data.some((notification) => !notification.read),
);

function dayLabel(day: string): string {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(year, month - 1, date).toLocaleDateString(
        document.documentElement.lang || undefined,
        { dateStyle: 'long' },
    );
}

function time(value: string): string {
    return new Date(value).toLocaleTimeString(
        document.documentElement.lang || undefined,
        { hour: '2-digit', minute: '2-digit' },
    );
}

function post(target: { url: string }): void {
    router.post(target.url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('notifications.centre.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-medium">
                {{ $t('notifications.centre.heading') }}
            </h1>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="settings()" data-test="notification-settings">
                        {{ $t('notifications.centre.settings') }}
                    </Link>
                </Button>
                <Button
                    :disabled="!hasUnread"
                    data-test="mark-all-read"
                    @click="post(readAll())"
                >
                    {{ $t('notifications.centre.mark_all_read') }}
                </Button>
            </div>
        </div>

        <p
            v-if="entries.data.length === 0"
            class="text-sm text-muted-foreground"
            data-test="notifications-empty"
        >
            {{ $t('notifications.centre.empty') }}
        </p>

        <section
            v-for="group in groups"
            :key="group.day"
            class="flex flex-col gap-2"
            data-test="notification-day"
        >
            <h2 class="text-sm font-medium text-muted-foreground">
                {{
                    group.relative
                        ? $t(`notifications.centre.${group.relative}`)
                        : dayLabel(group.day)
                }}
            </h2>
            <ul
                class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <li
                    v-for="notification in group.items"
                    :key="notification.id"
                    class="flex flex-wrap items-center justify-between gap-2 p-3"
                    :class="{ 'bg-muted/50': !notification.read }"
                    data-test="notification"
                    :data-read="notification.read"
                >
                    <button
                        type="button"
                        class="flex flex-1 flex-col items-start gap-1 text-left text-sm hover:underline"
                        data-test="notification-open"
                        @click="post(open(notification.id))"
                    >
                        <span :class="{ 'font-medium': !notification.read }">
                            {{
                                $t(
                                    notificationMessage(notification).key,
                                    notificationMessage(notification).params,
                                )
                            }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ time(notification.created_at) }}
                        </span>
                    </button>
                    <div class="flex items-center gap-2">
                        <Badge v-if="!notification.read" variant="secondary">
                            {{ $t('notifications.centre.unread') }}
                        </Badge>
                        <Button
                            v-if="notification.read"
                            variant="ghost"
                            size="sm"
                            data-test="notification-mark-unread"
                            @click="post(unread(notification.id))"
                        >
                            {{ $t('notifications.centre.mark_unread') }}
                        </Button>
                        <Button
                            v-else
                            variant="ghost"
                            size="sm"
                            data-test="notification-mark-read"
                            @click="post(read(notification.id))"
                        >
                            {{ $t('notifications.centre.mark_read') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <nav
            v-if="entries.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <Link
                v-if="entries.prev_page_url"
                :href="entries.prev_page_url"
                class="underline"
            >
                {{ $t('notifications.centre.previous') }}
            </Link>
            <span v-else />
            <span>{{ entries.current_page }} / {{ entries.last_page }}</span>
            <Link
                v-if="entries.next_page_url"
                :href="entries.next_page_url"
                class="underline"
            >
                {{ $t('notifications.centre.next') }}
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
