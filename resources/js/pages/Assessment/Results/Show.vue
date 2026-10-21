<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReviewQuestionCard from '@/components/assessment/ReviewQuestionCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { gradeLabel, passLabel } from '@/lib/grade';
import { index } from '@/routes/assessment/results';
import type { AttemptScore, AttemptStatus } from '@/types/attempts';
import type { ReviewQuestion } from '@/types/results';

/*
 * UC-017 step 3: the result of one of the student's attempts. Correct
 * answers come from the server only when the quiz allows it.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assessment.results.index.title', href: index() },
        ],
    },
});

defineProps<{
    quiz: { activity_id: string; title: string; course_space_title: string };
    attempt: {
        id: string;
        number: number;
        status: AttemptStatus;
        submitted_at: string | null;
        final: boolean;
    };
    score: AttemptScore | null;
    answersVisible: boolean;
    questions: ReviewQuestion[];
}>();
</script>

<template>
    <Head :title="quiz.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-medium" data-test="result-title">
                {{ quiz.title }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ quiz.course_space_title }} ·
                {{
                    $t('assessment.results.index.attempt', {
                        number: String(attempt.number),
                    })
                }}
            </p>
        </div>

        <Alert data-test="result-summary">
            <AlertDescription class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="score"
                        class="text-lg font-medium"
                        data-test="result-grade"
                    >
                        {{ gradeLabel(score.grade, $t) }}
                    </span>
                    <span
                        v-if="score && score.grade.scale !== 'points_pass_mark'"
                        class="text-muted-foreground"
                        data-test="result-points"
                    >
                        {{
                            $t('assessment.attempt.score', {
                                earned: score.earned,
                                max: score.max,
                            })
                        }}
                    </span>
                    <span
                        v-if="score && passLabel(score.grade, $t)"
                        data-test="result-passed"
                    >
                        {{ passLabel(score.grade, $t) }}
                    </span>
                    <Badge
                        :variant="attempt.final ? 'secondary' : 'outline'"
                        data-test="result-status"
                    >
                        {{
                            attempt.final
                                ? $t('assessment.grade.final')
                                : $t('assessment.grade.provisional')
                        }}
                    </Badge>
                </div>
                <span v-if="!score" data-test="result-score-hidden">
                    {{ $t('assessment.results.show.score_hidden') }}
                </span>
                <span v-if="!attempt.final" data-test="result-provisional">
                    {{ $t('assessment.results.show.provisional_notice') }}
                </span>
                <span v-if="!answersVisible" data-test="result-answers-hidden">
                    {{ $t('assessment.results.show.answers_hidden') }}
                </span>
            </AlertDescription>
        </Alert>

        <ReviewQuestionCard
            v-for="(question, i) in questions"
            :key="question.id"
            :question="question"
            :number="i + 1"
        />

        <div>
            <Button variant="outline" as-child>
                <Link :href="index()" data-test="result-back">
                    {{ $t('assessment.results.show.back') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
