<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import AttemptQuestionCard from '@/components/assessment/AttemptQuestionCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { sendJson } from '@/lib/activityWizard';
import { answerPayload, formatRemaining, isAnswered } from '@/lib/quizAttempt';
import { index } from '@/routes/activities';
import { submit as submitRoute } from '@/routes/assessment/attempts';
import { update as updateAnswer } from '@/routes/assessment/attempts/answers';
import { show as showQuiz } from '@/routes/assessment/quizzes';
import type {
    AttemptAnswer,
    AttemptDetails,
    AttemptQuestion,
    AttemptScore,
} from '@/types/attempts';

/*
 * UC-012: one attempt. Answers are autosaved as they change; the time left
 * comes from the server, which also decides when the attempt ends
 * (DATA-004): once the countdown reaches zero the page reloads and shows
 * the attempt as submitted.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

type SaveState = 'saving' | 'saved' | 'failed';

const props = defineProps<{
    quiz: { activity_id: string; title: string };
    attempt: AttemptDetails;
    questions: AttemptQuestion[];
    answers: Record<string, AttemptAnswer>;
    score: AttemptScore | null;
}>();

const SAVE_DELAY_MS = 600;

const inProgress = computed(() => props.attempt.status === 'in_progress');
const answers = reactive<Record<string, AttemptAnswer>>({ ...props.answers });
const saveStates = reactive<Record<string, SaveState>>({});
const timers = new Map<string, ReturnType<typeof setTimeout>>();
const inFlight = new Set<Promise<void>>();
const confirming = ref(false);
const submitting = ref(false);
const closedNotice = ref(false);

const remaining = ref<number | null>(props.attempt.remaining_seconds);
let countdown: ReturnType<typeof setInterval> | null = null;

const answeredCount = computed(
    () => props.questions.filter((q) => isAnswered(q, answers[q.id])).length,
);

function reloadAttempt(): void {
    router.reload();
}

async function save(question: AttemptQuestion): Promise<void> {
    saveStates[question.id] = 'saving';
    const result = await sendJson<{ saved_at: string | null }>(
        'PUT',
        updateAnswer({ attempt: props.attempt.id, quizQuestion: question.id })
            .url,
        answerPayload(question, answers[question.id]),
    );

    if (result.ok) {
        saveStates[question.id] = 'saved';

        return;
    }

    saveStates[question.id] = 'failed';

    // The attempt has ended on the server (time up, closed or submitted).
    if (result.status === 409) {
        closedNotice.value = true;
        reloadAttempt();
    }
}

function queueSave(question: AttemptQuestion): void {
    const pending = timers.get(question.id);

    if (pending) {
        clearTimeout(pending);
    }

    timers.set(
        question.id,
        setTimeout(() => {
            timers.delete(question.id);
            const promise = save(question).finally(() =>
                inFlight.delete(promise),
            );
            inFlight.add(promise);
        }, SAVE_DELAY_MS),
    );
}

function onChange(question: AttemptQuestion, answer: AttemptAnswer): void {
    answers[question.id] = answer;
    queueSave(question);
}

async function flushSaves(): Promise<void> {
    for (const [id, timer] of timers) {
        clearTimeout(timer);
        timers.delete(id);
        const question = props.questions.find((q) => q.id === id);

        if (question) {
            inFlight.add(save(question));
        }
    }

    await Promise.allSettled([...inFlight]);
}

async function submitAttempt(): Promise<void> {
    submitting.value = true;
    await flushSaves();
    router.post(
        submitRoute(props.attempt.id).url,
        {},
        {
            onFinish: () => {
                submitting.value = false;
                confirming.value = false;
            },
        },
    );
}

onMounted(() => {
    if (!inProgress.value || remaining.value === null) {
        return;
    }

    countdown = setInterval(() => {
        if (remaining.value === null) {
            return;
        }

        remaining.value -= 1;

        if (remaining.value <= 0) {
            if (countdown) {
                clearInterval(countdown);
            }

            // The server submits the attempt; show its result.
            setTimeout(reloadAttempt, 1000);
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (countdown) {
        clearInterval(countdown);
    }
});
</script>

<template>
    <Head :title="quiz.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-xl font-medium" data-test="attempt-title">
                    {{ quiz.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('assessment.attempt.quiz.attempt_number', {
                            number: String(attempt.number),
                        })
                    }}
                    ·
                    {{ $t(`assessment.attempt.statuses.${attempt.status}`) }}
                </p>
            </div>
            <div
                v-if="inProgress"
                class="rounded-md border px-3 py-1 text-sm tabular-nums"
                data-test="attempt-timer"
                aria-live="polite"
            >
                {{
                    remaining === null
                        ? $t('assessment.attempt.take.no_time_limit')
                        : $t('assessment.attempt.take.time_left', {
                              time: formatRemaining(remaining),
                          })
                }}
            </div>
        </div>

        <Alert v-if="closedNotice" variant="destructive">
            <AlertDescription>{{
                $t('assessment.attempt.errors.closed')
            }}</AlertDescription>
        </Alert>

        <Alert v-if="!inProgress" data-test="attempt-result">
            <AlertDescription class="flex flex-col gap-1">
                <span v-if="score" data-test="attempt-score">
                    {{
                        $t('assessment.attempt.your_score', {
                            earned: score.earned,
                            max: score.max,
                        })
                    }}
                </span>
                <span v-if="score?.provisional" data-test="attempt-provisional">
                    {{ $t('assessment.attempt.take.provisional_notice') }}
                </span>
                <span v-if="!score" data-test="attempt-score-hidden">
                    {{ $t('assessment.attempt.take.score_hidden') }}
                </span>
            </AlertDescription>
        </Alert>

        <AttemptQuestionCard
            v-for="(question, i) in questions"
            :key="question.id"
            :question="question"
            :number="i + 1"
            :answer="answers[question.id]"
            :disabled="!inProgress || submitting"
            :save-state="saveStates[question.id] ?? null"
            @change="(answer: AttemptAnswer) => onChange(question, answer)"
        />

        <div class="flex flex-wrap items-center gap-3">
            <Button
                v-if="inProgress"
                :disabled="submitting"
                data-test="attempt-submit"
                @click="confirming = true"
            >
                {{ $t('assessment.attempt.take.submit') }}
            </Button>
            <Button variant="outline" as-child>
                <Link
                    :href="showQuiz(quiz.activity_id)"
                    data-test="attempt-back"
                >
                    {{ $t('assessment.attempt.take.back') }}
                </Link>
            </Button>
        </div>

        <Dialog
            :open="confirming"
            @update:open="(open: boolean) => (confirming = open)"
        >
            <DialogContent data-test="attempt-submit-dialog">
                <DialogTitle>
                    {{ $t('assessment.attempt.take.confirm_submit') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t('assessment.attempt.take.confirm_submit_body', {
                            answered: String(answeredCount),
                            total: String(questions.length),
                        })
                    }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('assessment.attempt.take.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        :disabled="submitting"
                        data-test="attempt-submit-confirm"
                        @click="submitAttempt"
                    >
                        {{ $t('assessment.attempt.take.submit') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
