<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    close,
    destroy,
    edit,
    index,
    publish,
    submissions,
} from '@/routes/activities';
import { show as showPoll } from '@/routes/activities/polls';
import { show as showProposals } from '@/routes/activities/proposals';
import { show as showSlots } from '@/routes/activities/slots';
import { show as showTopics } from '@/routes/activities/topics';
import { show as showQuiz } from '@/routes/assessment/quizzes';
import type { ActivityDetails } from '@/types/activities';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    activity: ActivityDetails;
    can: {
        update: boolean;
        publish: boolean;
        close: boolean;
        viewSubmissions: boolean;
        participate: boolean;
        delete: boolean;
    };
}>();

const page = usePage();
const deleteError = computed(
    () => (page.props.errors as Record<string, string>).activity,
);

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function publishActivity(): void {
    router.post(publish(props.activity.id).url);
}

function closeActivity(): void {
    router.post(close(props.activity.id).url);
}

// UC-040: deletion cannot be undone, so the teacher confirms it.
const confirmingDelete = ref(false);

function deleteActivity(): void {
    router.visit(destroy(props.activity.id), {
        onFinish: () => {
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <Head :title="activity.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="activity-title">
                    {{ activity.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {{ $t(`activities.types.${activity.type}`) }}
                    </Badge>
                    <Badge
                        :variant="
                            activity.status === 'published'
                                ? 'secondary'
                                : 'outline'
                        "
                        data-test="activity-status"
                    >
                        {{ $t(`activities.statuses.${activity.status}`) }}
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(activity.id)" data-test="activity-edit">
                        {{ $t('activities.show.edit') }}
                    </Link>
                </Button>
                <Button
                    v-if="can.participate && activity.type === 'quiz'"
                    as-child
                >
                    <Link
                        :href="showQuiz(activity.id)"
                        data-test="activity-open-quiz"
                    >
                        {{ $t('activities.show.open_quiz') }}
                    </Link>
                </Button>
                <Button
                    v-if="
                        activity.type === 'poll' &&
                        activity.status !== 'draft' &&
                        (can.participate || can.viewSubmissions)
                    "
                    as-child
                >
                    <Link
                        :href="showPoll(activity.id)"
                        data-test="activity-open-poll"
                    >
                        {{ $t('activities.show.open_poll') }}
                    </Link>
                </Button>
                <Button
                    v-if="
                        activity.type === 'topic_selection' &&
                        activity.status !== 'draft' &&
                        (can.participate || can.viewSubmissions)
                    "
                    as-child
                >
                    <Link
                        :href="showTopics(activity.id)"
                        data-test="activity-open-topics"
                    >
                        {{ $t('activities.show.open_topics') }}
                    </Link>
                </Button>
                <Button
                    v-if="
                        activity.type === 'topic_proposal' &&
                        activity.status !== 'draft' &&
                        (can.participate || can.viewSubmissions)
                    "
                    as-child
                >
                    <Link
                        :href="showProposals(activity.id)"
                        data-test="activity-open-proposals"
                    >
                        {{ $t('activities.show.open_proposals') }}
                    </Link>
                </Button>
                <Button
                    v-if="
                        activity.type === 'slot_booking' &&
                        activity.status !== 'draft' &&
                        (can.participate || can.viewSubmissions)
                    "
                    as-child
                >
                    <Link
                        :href="showSlots(activity.id)"
                        data-test="activity-open-slots"
                    >
                        {{ $t('activities.show.open_slots') }}
                    </Link>
                </Button>
                <!-- A poll is anonymous: it has results, no submissions (SEC-009). -->
                <Button
                    v-if="can.viewSubmissions && activity.type !== 'poll'"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="submissions(activity.id)"
                        data-test="activity-submissions"
                    >
                        {{ $t('activities.show.submissions') }}
                    </Link>
                </Button>
                <Button
                    v-if="can.publish"
                    data-test="activity-publish"
                    @click="publishActivity"
                >
                    {{ $t('activities.show.publish') }}
                </Button>
                <Button
                    v-if="can.close"
                    variant="destructive"
                    data-test="activity-close"
                    @click="closeActivity"
                >
                    {{ $t('activities.show.close') }}
                </Button>
                <Button
                    v-if="can.delete"
                    variant="destructive"
                    data-test="activity-delete"
                    @click="confirmingDelete = true"
                >
                    {{ $t('activities.show.delete') }}
                </Button>
            </div>
        </div>

        <Alert
            v-if="deleteError"
            variant="destructive"
            data-test="delete-error"
        >
            <AlertDescription>{{ deleteError }}</AlertDescription>
        </Alert>

        <Alert v-if="activity.status === 'draft' && can.update">
            <AlertDescription>{{
                $t('activities.show.draft_notice')
            }}</AlertDescription>
        </Alert>
        <Alert v-if="activity.status === 'closed'">
            <AlertDescription>{{
                $t('activities.show.closed_notice')
            }}</AlertDescription>
        </Alert>

        <p
            v-if="activity.description"
            class="text-sm whitespace-pre-line"
            data-test="activity-description"
        >
            {{ activity.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('activities.show.course_space') }}
            </dt>
            <dd>{{ activity.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('activities.show.deadline') }}
            </dt>
            <dd>
                {{
                    activity.deadline_at
                        ? formatDate(activity.deadline_at)
                        : $t('activities.show.no_deadline')
                }}
            </dd>
            <template v-if="activity.published_at">
                <dt class="text-muted-foreground">
                    {{ $t('activities.show.published_at') }}
                </dt>
                <dd>{{ formatDate(activity.published_at) }}</dd>
            </template>
        </dl>

        <Dialog v-model:open="confirmingDelete">
            <DialogContent data-test="delete-dialog">
                <DialogTitle>
                    {{ $t('activities.show.confirm_delete_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t('activities.show.confirm_delete_text', {
                            title: activity.title,
                        })
                    }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" data-test="delete-cancel">
                            {{ $t('activities.show.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        data-test="delete-confirm"
                        @click="deleteActivity"
                    >
                        {{ $t('activities.show.confirm_delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
