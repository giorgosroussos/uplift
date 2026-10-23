<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    index,
    show,
    submissions as submissionsRoute,
} from '@/routes/activities';
import { show as showPoll } from '@/routes/activities/polls';
import type {
    ActivityKind,
    ActivityStatus,
    Submission,
    SubmissionStatus,
} from '@/types/activities';

const props = defineProps<{
    activity: {
        id: string;
        title: string;
        type: ActivityKind;
        status: ActivityStatus;
    };
    status: SubmissionStatus | null;
    statuses: SubmissionStatus[];
    submissions: Submission[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

function filterUrl(status: SubmissionStatus | null) {
    return submissionsRoute(props.activity.id, {
        query: status ? { status } : {},
    });
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head :title="$t('activities.submissions.title')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-medium">
                {{ $t('activities.submissions.title') }}
            </h1>
            <Link
                :href="show(activity.id)"
                class="text-sm text-muted-foreground hover:underline"
            >
                {{ activity.title }}
            </Link>
        </div>

        <!-- A poll is anonymous: no submissions list, only its results (SEC-009, SCOPE-026). -->
        <div
            v-if="activity.type === 'poll'"
            class="flex flex-col items-start gap-3"
            data-test="submissions-poll"
        >
            <p class="text-sm text-muted-foreground">
                {{ $t('activities.submissions.poll_anonymous') }}
            </p>
            <Button as-child>
                <Link
                    :href="showPoll(activity.id)"
                    data-test="submissions-poll-results"
                >
                    {{ $t('activities.submissions.poll_results') }}
                </Link>
            </Button>
        </div>

        <template v-else>
            <nav
                class="flex flex-wrap items-center gap-2"
                :aria-label="$t('activities.submissions.filter')"
            >
                <Button
                    size="sm"
                    :variant="status === null ? 'default' : 'outline'"
                    as-child
                >
                    <Link :href="filterUrl(null)" data-test="filter-all">
                        {{ $t('activities.submissions.all') }}
                    </Link>
                </Button>
                <Button
                    v-for="option in statuses"
                    :key="option"
                    size="sm"
                    :variant="status === option ? 'default' : 'outline'"
                    as-child
                >
                    <Link
                        :href="filterUrl(option)"
                        :data-test="`filter-${option}`"
                    >
                        {{ $t(`activities.submissions.statuses.${option}`) }}
                    </Link>
                </Button>
            </nav>

            <p
                v-if="submissions.length === 0"
                class="text-sm text-muted-foreground"
                data-test="submissions-empty"
            >
                {{
                    status === null
                        ? $t('activities.submissions.empty')
                        : $t('activities.submissions.empty_filtered')
                }}
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 pr-4 font-medium">
                                {{ $t('activities.submissions.participant') }}
                            </th>
                            <th class="py-2 pr-4 font-medium">
                                {{ $t('activities.submissions.submitted_at') }}
                            </th>
                            <th class="py-2 pr-4 font-medium">
                                {{ $t('activities.submissions.status') }}
                            </th>
                            <th class="py-2 pr-4 font-medium">
                                {{ $t('activities.submissions.score') }}
                            </th>
                            <th class="py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="submission in submissions"
                            :key="submission.id"
                            class="border-b last:border-0"
                            data-test="submission"
                        >
                            <td class="py-2 pr-4">
                                {{ submission.participant }}
                            </td>
                            <td class="py-2 pr-4">
                                {{ formatDate(submission.submitted_at) }}
                            </td>
                            <td class="py-2 pr-4">
                                <Badge
                                    :variant="
                                        submission.status === 'needs_grading'
                                            ? 'destructive'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        $t(
                                            `activities.submissions.statuses.${submission.status}`,
                                        )
                                    }}
                                </Badge>
                            </td>
                            <td class="py-2 pr-4">
                                <template
                                    v-if="
                                        submission.earned_points !== null &&
                                        submission.max_points !== null
                                    "
                                >
                                    {{ submission.earned_points }} /
                                    {{ submission.max_points }}
                                </template>
                            </td>
                            <td class="py-2 text-right">
                                <Link
                                    v-if="submission.url"
                                    :href="submission.url"
                                    class="underline"
                                    data-test="submission-open"
                                >
                                    {{ $t('activities.submissions.open') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
