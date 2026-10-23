<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GradeAnswerForm from '@/components/assessment/GradeAnswerForm.vue';
import ReviewQuestionCard from '@/components/assessment/ReviewQuestionCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { gradeLabel, passLabel } from '@/lib/grade';
import { index, submissions } from '@/routes/activities';
import type { AttemptScore, AttemptStatus } from '@/types/attempts';
import type { ReviewQuestion } from '@/types/results';

/*
 * UC-026 / UC-028: the owning teacher grades one attempt. Every answered
 * question can be graded (free text) or regraded (auto-graded); once no
 * answer waits for grading the score is final.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

defineProps<{
    quiz: { activity_id: string; title: string };
    attempt: {
        id: string;
        number: number;
        student: string | null;
        group: string | null;
        status: AttemptStatus;
        submitted_at: string | null;
        final: boolean;
    };
    score: AttemptScore | null;
    canGrade: boolean;
    questions: ReviewQuestion[];
}>();

function gradable(
    question: ReviewQuestion,
): question is ReviewQuestion & { answer_id: string } {
    return question.answer_id !== null;
}
</script>

<template>
    <Head :title="$t('assessment.grading.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-medium">
                {{ $t('assessment.grading.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ quiz.title }} ·
                {{
                    $t('assessment.results.index.attempt', {
                        number: String(attempt.number),
                    })
                }}
            </p>
            <p v-if="attempt.group" class="text-sm" data-test="grading-group">
                {{ $t('assessment.grading.group') }}:
                {{ attempt.group }}
            </p>
            <p v-else class="text-sm" data-test="grading-student">
                {{ $t('assessment.grading.student') }}:
                {{ attempt.student }}
            </p>
        </div>

        <Alert v-if="!canGrade" data-test="grading-in-progress">
            <AlertDescription>
                {{ $t('assessment.grading.in_progress_notice') }}
            </AlertDescription>
        </Alert>

        <Alert v-if="score" data-test="grading-summary">
            <AlertDescription class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-lg font-medium" data-test="grading-grade">
                        {{ gradeLabel(score.grade, $t) }}
                    </span>
                    <span
                        v-if="score.grade.scale !== 'points_pass_mark'"
                        class="text-muted-foreground"
                    >
                        {{
                            $t('assessment.attempt.score', {
                                earned: score.earned,
                                max: score.max,
                            })
                        }}
                    </span>
                    <span v-if="passLabel(score.grade, $t)">
                        {{ passLabel(score.grade, $t) }}
                    </span>
                    <Badge
                        :variant="attempt.final ? 'secondary' : 'outline'"
                        data-test="grading-status"
                    >
                        {{
                            attempt.final
                                ? $t('assessment.grade.final')
                                : $t('assessment.grade.provisional')
                        }}
                    </Badge>
                </div>
                <span>
                    {{
                        attempt.final
                            ? $t('assessment.grading.final_notice')
                            : $t('assessment.grading.provisional_notice')
                    }}
                </span>
            </AlertDescription>
        </Alert>

        <ReviewQuestionCard
            v-for="(question, i) in questions"
            :key="question.id"
            :question="question"
            :number="i + 1"
        >
            <GradeAnswerForm
                v-if="canGrade && gradable(question)"
                :key="`${question.id}-${question.points_awarded}-${question.grading_status}`"
                :question="question"
                :number="i + 1"
            />
        </ReviewQuestionCard>

        <div>
            <Button variant="outline" as-child>
                <Link
                    :href="submissions(quiz.activity_id)"
                    data-test="grading-back"
                >
                    {{ $t('assessment.grading.back') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
