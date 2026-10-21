<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    emptyAnswer,
    matchOf,
    setMatch,
    toggleOption,
} from '@/lib/quizAttempt';
import type { AttemptAnswer, AttemptQuestion } from '@/types/attempts';

/**
 * One question of an attempt (UC-012) in any of the five types. Emits the
 * whole changed answer; the page autosaves it.
 */
const props = defineProps<{
    question: AttemptQuestion;
    number: number;
    answer: AttemptAnswer | undefined;
    disabled: boolean;
    saveState?: 'saving' | 'saved' | 'failed' | null;
}>();

const emit = defineEmits<{
    change: [answer: AttemptAnswer];
}>();

const current = computed<AttemptAnswer>(() => props.answer ?? emptyAnswer());
const inputName = computed(() => `question-${props.question.id}`);

function onOption(optionId: string): void {
    emit('change', toggleOption(props.question, current.value, optionId));
}

function onMatch(leftId: string, event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    emit(
        'change',
        setMatch(current.value, leftId, value === '' ? null : value),
    );
}

function onText(event: Event): void {
    emit('change', {
        ...current.value,
        text: (event.target as HTMLTextAreaElement).value,
    });
}
</script>

<template>
    <fieldset
        class="flex flex-col gap-3 rounded-lg border p-4"
        :data-test="`attempt-question-${number}`"
        :disabled="disabled"
    >
        <legend class="sr-only">
            {{
                $t('assessment.attempt.take.question_number', {
                    number: String(number),
                })
            }}
        </legend>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="font-medium whitespace-pre-line">
                <span class="text-muted-foreground">{{ number }}.</span>
                {{ question.prompt }}
            </p>
            <div class="flex items-center gap-2">
                <span
                    v-if="saveState"
                    class="text-xs text-muted-foreground"
                    :data-test="`attempt-save-${number}`"
                >
                    {{ $t(`assessment.attempt.take.save_${saveState}`) }}
                </span>
                <Badge variant="outline">
                    {{
                        $t('assessment.quiz.questions.points', {
                            points: question.points,
                        })
                    }}
                </Badge>
            </div>
        </div>

        <p
            v-if="question.type === 'multiple_choice'"
            class="text-xs text-muted-foreground"
        >
            {{ $t('assessment.attempt.take.multiple_hint') }}
        </p>

        <div v-if="question.options.length > 0" class="flex flex-col gap-2">
            <label
                v-for="(option, index) in question.options"
                :key="option.id"
                class="flex items-center gap-2 text-sm"
            >
                <input
                    :type="
                        question.type === 'multiple_choice'
                            ? 'checkbox'
                            : 'radio'
                    "
                    :name="inputName"
                    :value="option.id"
                    :checked="current.option_ids.includes(option.id)"
                    class="size-4"
                    :data-test="`attempt-option-${number}-${index + 1}`"
                    @change="onOption(option.id)"
                />
                <span v-if="question.type === 'true_false'">{{
                    $t(`assessment.true_false.${option.text}`)
                }}</span>
                <span v-else>{{ option.text }}</span>
            </label>
        </div>

        <div
            v-if="question.type === 'matching'"
            class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:items-center"
        >
            <template v-for="(item, index) in question.left" :key="item.id">
                <label :for="`${inputName}-${item.id}`" class="text-sm">
                    {{ item.text }}
                </label>
                <select
                    :id="`${inputName}-${item.id}`"
                    :value="matchOf(current, item.id)"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    :data-test="`attempt-match-${number}-${index + 1}`"
                    @change="onMatch(item.id, $event)"
                >
                    <option value="">
                        {{ $t('assessment.attempt.take.choose_match') }}
                    </option>
                    <option
                        v-for="right in question.right"
                        :key="right.id"
                        :value="right.id"
                    >
                        {{ right.text }}
                    </option>
                </select>
            </template>
        </div>

        <textarea
            v-if="question.type === 'free_text'"
            :value="current.text ?? ''"
            rows="4"
            maxlength="10000"
            class="rounded-md border bg-background p-2 text-sm"
            :aria-label="$t('assessment.attempt.take.your_answer')"
            :data-test="`attempt-text-${number}`"
            @input="onText"
        />
    </fieldset>
</template>
