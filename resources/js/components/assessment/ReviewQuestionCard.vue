<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { ReviewOption, ReviewQuestion } from '@/types/results';

/**
 * One question of a submitted attempt with the answer given (UC-017,
 * UC-026): selected options or matches or the text, the grading status,
 * the points awarded and the teacher's feedback. Correct answers appear
 * only when the server sent them. The default slot holds the grading form.
 */
const props = defineProps<{
    question: ReviewQuestion;
    number: number;
}>();

const answered = computed(
    () =>
        props.question.options.some((o) => o.selected) ||
        props.question.matches.some((m) => m.chosen !== null) ||
        (props.question.text ?? '').trim() !== '',
);

function optionText(option: ReviewOption, t: (key: string) => string): string {
    return props.question.type === 'true_false'
        ? t(`assessment.true_false.${option.text}`)
        : option.text;
}
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-lg border p-4"
        :data-test="`review-question-${number}`"
        :aria-label="
            $t('assessment.review.question_number', { number: String(number) })
        "
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="font-medium whitespace-pre-line">
                <span class="text-muted-foreground">{{ number }}.</span>
                {{ question.prompt }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <Badge
                    v-if="question.grading_status"
                    :variant="
                        question.grading_status === 'pending'
                            ? 'destructive'
                            : 'secondary'
                    "
                    :data-test="`review-status-${number}`"
                >
                    {{
                        $t(
                            `assessment.review.grading_statuses.${question.grading_status}`,
                        )
                    }}
                </Badge>
                <Badge variant="outline" :data-test="`review-points-${number}`">
                    {{
                        question.points_awarded === null
                            ? $t('assessment.quiz.questions.points', {
                                  points: question.points,
                              })
                            : $t('assessment.review.points_awarded', {
                                  awarded: question.points_awarded,
                                  points: question.points,
                              })
                    }}
                </Badge>
            </div>
        </div>

        <p
            v-if="!answered"
            class="text-sm text-muted-foreground"
            :data-test="`review-no-answer-${number}`"
        >
            {{ $t('assessment.review.no_answer') }}
        </p>

        <ul v-if="question.options.length > 0" class="flex flex-col gap-1">
            <li
                v-for="option in question.options"
                :key="option.id"
                class="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm"
                :class="{
                    'border-primary': option.selected,
                }"
                :data-test="`review-option-${number}`"
            >
                <span>{{ optionText(option, $t) }}</span>
                <Badge v-if="option.selected" variant="default">
                    {{ $t('assessment.review.selected') }}
                </Badge>
                <Badge
                    v-if="option.correct === true"
                    variant="secondary"
                    data-test="review-correct"
                >
                    {{ $t('assessment.review.correct') }}
                </Badge>
            </li>
        </ul>

        <ul v-if="question.matches.length > 0" class="flex flex-col gap-1">
            <li
                v-for="(match, i) in question.matches"
                :key="i"
                class="flex flex-col gap-1 rounded-md border px-3 py-2 text-sm"
                :data-test="`review-match-${number}`"
            >
                <span>
                    {{
                        $t('assessment.review.matched_to', {
                            left: match.left,
                            right:
                                match.chosen ??
                                $t('assessment.review.no_answer'),
                        })
                    }}
                </span>
                <span
                    v-if="match.correct !== null && match.is_correct === false"
                    class="text-muted-foreground"
                    data-test="review-correct"
                >
                    {{
                        $t('assessment.review.correct_answer', {
                            answer: match.correct,
                        })
                    }}
                </span>
            </li>
        </ul>

        <div
            v-if="question.type === 'free_text' && answered"
            class="flex flex-col gap-1"
        >
            <span class="text-xs text-muted-foreground">
                {{ $t('assessment.review.your_answer') }}
            </span>
            <p
                class="rounded-md border bg-muted/40 p-2 text-sm whitespace-pre-line"
                :data-test="`review-text-${number}`"
            >
                {{ question.text }}
            </p>
        </div>

        <div v-if="question.feedback" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">
                {{ $t('assessment.review.feedback') }}
            </span>
            <p
                class="text-sm whitespace-pre-line"
                :data-test="`review-feedback-${number}`"
            >
                {{ question.feedback }}
            </p>
        </div>

        <slot />
    </section>
</template>
