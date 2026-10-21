<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/activities';
import type { Auth } from '@/types';
import type { ActivitySummary } from '@/types/activities';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

defineProps<{
    activities: ActivitySummary[];
    canCreate: boolean;
}>();

const page = usePage<{ auth: Auth }>();
const role = computed(() => page.props.auth.user.role);

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head :title="$t('activities.index.title')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-medium">
                {{ $t('activities.index.title') }}
            </h1>
            <Button v-if="canCreate" as-child>
                <Link :href="create()" data-test="create-activity">
                    {{ $t('activities.index.create') }}
                </Link>
            </Button>
        </div>

        <p
            v-if="activities.length === 0"
            class="text-sm text-muted-foreground"
            data-test="activities-empty"
        >
            {{ $t(`activities.index.empty_${role}`) }}
        </p>

        <ul v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="activity in activities"
                :key="activity.id"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                data-test="activity"
            >
                <Link
                    :href="show(activity.id)"
                    class="font-medium hover:underline"
                    data-test="activity-link"
                >
                    {{ activity.title }}
                </Link>
                <div
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <Badge variant="outline">
                        {{ $t(`activities.types.${activity.type}`) }}
                    </Badge>
                    <Badge
                        :variant="
                            activity.status === 'published'
                                ? 'secondary'
                                : 'outline'
                        "
                    >
                        {{ $t(`activities.statuses.${activity.status}`) }}
                    </Badge>
                    <span>{{ activity.course_space_title }}</span>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ $t('activities.index.deadline') }}:
                    {{
                        activity.deadline_at
                            ? formatDate(activity.deadline_at)
                            : $t('activities.index.no_deadline')
                    }}
                </p>
            </li>
        </ul>
    </div>
</template>
