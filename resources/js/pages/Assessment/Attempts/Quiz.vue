<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show as showActivity } from '@/routes/activities';
import { show as showAttempt, store } from '@/routes/assessment/attempts';
import { index as groupsIndex } from '@/routes/courses/groups';
import type {
    AttemptSummary,
    GroupChoice,
    StudentQuiz,
} from '@/types/attempts';

/*
 * UC-012: a self-paced quiz as the student sees it: its rules, their own
 * attempts with the scores they may see, and starting or continuing an
 * attempt. In a group quiz (SCOPE-012, SCOPE-025) the student picks which
 * of their groups the attempt belongs to; groups that may not start one
 * now show why.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    quiz: StudentQuiz;
    attempts: AttemptSummary[];
    attemptsLeft: number;
    inProgressId: string | null;
    canStart: boolean;
    /** Null unless the quiz is a group quiz. */
    groups: GroupChoice[] | null;
}>();

const page = usePage();
const refusal = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.attempt,
);
const groupError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.group_id,
);
const starting = ref(false);
const groupId = ref<string>(
    props.groups?.find((group) => group.refusal === null)?.id ?? '',
);
const chosenGroup = computed(
    () => props.groups?.find((group) => group.id === groupId.value) ?? null,
);
const canStartNow = computed(
    () =>
        props.canStart &&
        (!props.quiz.is_group ||
            (chosenGroup.value !== null && chosenGroup.value.refusal === null)),
);

const deadlinePassed = computed(
    () =>
        props.quiz.deadline_at !== null &&
        new Date(props.quiz.deadline_at).getTime() <= Date.now(),
);

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function start(): void {
    starting.value = true;
    router.post(
        store(props.quiz.activity_id).url,
        props.quiz.is_group ? { group_id: groupId.value } : {},
        {
            onFinish: () => {
                starting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="quiz.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-medium" data-test="quiz-title">
                {{ quiz.title }}
            </h1>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{{
                    $t('activities.types.quiz')
                }}</Badge>
                <Badge variant="outline">
                    {{ $t(`activities.statuses.${quiz.status}`) }}
                </Badge>
            </div>
        </div>

        <p v-if="quiz.description" class="text-sm whitespace-pre-line">
            {{ quiz.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('activities.show.course_space') }}
            </dt>
            <dd>{{ quiz.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('activities.show.deadline') }}
            </dt>
            <dd>
                {{
                    quiz.deadline_at
                        ? formatDate(quiz.deadline_at)
                        : $t('activities.show.no_deadline')
                }}
            </dd>
            <dt class="text-muted-foreground">
                {{ $t('assessment.attempt.quiz.time_limit') }}
            </dt>
            <dd>
                {{
                    quiz.time_limit_minutes
                        ? $t('assessment.attempt.quiz.minutes', {
                              minutes: String(quiz.time_limit_minutes),
                          })
                        : $t('assessment.attempt.quiz.no_time_limit')
                }}
            </dd>
            <dt class="text-muted-foreground">
                {{ $t('assessment.attempt.quiz.questions') }}
            </dt>
            <dd>{{ quiz.question_count }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('assessment.attempt.quiz.attempts') }}
            </dt>
            <dd data-test="quiz-attempts-left">
                {{
                    $t('assessment.attempt.quiz.attempts_left', {
                        left: String(attemptsLeft),
                        max: String(quiz.max_attempts),
                    })
                }}
            </dd>
        </dl>

        <Alert v-if="quiz.negative_marking">
            <AlertDescription>
                {{
                    $t('assessment.attempt.quiz.negative_marking', {
                        fraction: quiz.negative_mark_fraction ?? '',
                    })
                }}
            </AlertDescription>
        </Alert>

        <Alert v-if="quiz.is_group" data-test="quiz-group-notice">
            <AlertDescription class="flex flex-col gap-1">
                <span>{{ $t('assessment.attempt.quiz.group_quiz') }}</span>
                <span v-if="quiz.group_size">
                    {{
                        $t('assessment.attempt.quiz.group_size', {
                            size: String(quiz.group_size),
                        })
                    }}
                </span>
            </AlertDescription>
        </Alert>

        <Alert v-if="refusal" variant="destructive" data-test="attempt-refused">
            <AlertDescription>{{ refusal }}</AlertDescription>
        </Alert>
        <Alert
            v-if="groupError"
            variant="destructive"
            data-test="attempt-group-refused"
        >
            <AlertDescription>{{ groupError }}</AlertDescription>
        </Alert>

        <div
            v-if="quiz.is_group && !inProgressId && groups"
            class="flex flex-col gap-2"
        >
            <template v-if="groups.length > 0">
                <label for="attempt-group" class="text-sm font-medium">
                    {{ $t('assessment.attempt.quiz.choose_group') }}
                </label>
                <select
                    id="attempt-group"
                    v-model="groupId"
                    class="h-9 max-w-md rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                    data-test="attempt-group-select"
                >
                    <option value="" disabled>
                        {{ $t('assessment.attempt.quiz.group') }}
                    </option>
                    <option
                        v-for="group in groups"
                        :key="group.id"
                        :value="group.id"
                    >
                        {{
                            $t('assessment.attempt.quiz.group_option', {
                                name: group.name,
                                members: String(group.members),
                                left: String(group.attempts_left),
                            })
                        }}
                    </option>
                </select>
                <p
                    v-if="chosenGroup?.refusal"
                    class="text-sm text-muted-foreground"
                    data-test="attempt-group-reason"
                >
                    {{
                        $t(`assessment.attempt.refused.${chosenGroup.refusal}`)
                    }}
                </p>
            </template>
            <p
                v-else
                class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                data-test="attempt-no-groups"
            >
                {{ $t('assessment.attempt.quiz.no_groups') }}
                <Link
                    :href="groupsIndex(quiz.course_space_id)"
                    class="underline underline-offset-4"
                >
                    {{ $t('assessment.attempt.quiz.open_groups') }}
                </Link>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button v-if="inProgressId" as-child>
                <Link
                    :href="showAttempt(inProgressId)"
                    data-test="attempt-continue"
                >
                    {{ $t('assessment.attempt.quiz.continue') }}
                </Link>
            </Button>
            <Button
                v-else-if="canStart"
                :disabled="starting || !canStartNow"
                data-test="attempt-start"
                @click="start"
            >
                {{ $t('assessment.attempt.quiz.start') }}
            </Button>
            <p
                v-else
                class="text-sm text-muted-foreground"
                data-test="attempt-unavailable"
            >
                {{
                    quiz.status === 'closed'
                        ? $t('assessment.attempt.quiz.closed')
                        : deadlinePassed
                          ? $t('assessment.attempt.quiz.deadline_passed')
                          : quiz.is_group
                            ? $t('assessment.attempt.quiz.no_group_can_start')
                            : $t('assessment.attempt.quiz.no_attempts_left')
                }}
            </p>
            <Button variant="outline" as-child>
                <Link :href="showActivity(quiz.activity_id)">
                    {{ $t('assessment.attempt.quiz.back') }}
                </Link>
            </Button>
        </div>

        <section v-if="attempts.length > 0" class="flex flex-col gap-3">
            <h2 class="text-base font-medium">
                {{ $t('assessment.attempt.quiz.your_attempts') }}
            </h2>
            <ul class="flex flex-col divide-y rounded-lg border">
                <li
                    v-for="attempt in attempts"
                    :key="attempt.id"
                    class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm"
                    :data-test="`attempt-row-${attempt.number}`"
                >
                    <div class="flex flex-col gap-1">
                        <Link
                            :href="showAttempt(attempt.id)"
                            class="font-medium underline-offset-4 hover:underline"
                        >
                            {{
                                $t('assessment.attempt.quiz.attempt_number', {
                                    number: String(attempt.number),
                                })
                            }}
                        </Link>
                        <span class="text-muted-foreground">
                            {{ formatDate(attempt.started_at) }}
                        </span>
                        <span
                            v-if="attempt.group"
                            class="text-muted-foreground"
                            data-test="attempt-group-name"
                        >
                            {{
                                $t('assessment.attempt.quiz.group_attempt', {
                                    group: attempt.group,
                                })
                            }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="outline">
                            {{
                                $t(
                                    `assessment.attempt.statuses.${attempt.status}`,
                                )
                            }}
                        </Badge>
                        <span v-if="attempt.score" data-test="attempt-score">
                            {{
                                $t('assessment.attempt.score', {
                                    earned: attempt.score.earned,
                                    max: attempt.score.max,
                                })
                            }}
                            <span
                                v-if="attempt.score.provisional"
                                class="text-muted-foreground"
                            >
                                {{ $t('assessment.attempt.provisional') }}
                            </span>
                        </span>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
