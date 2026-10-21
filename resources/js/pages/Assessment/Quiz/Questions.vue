<script setup lang="ts">
import {
    ChevronDown,
    ChevronUp,
    GripVertical,
    Library,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import QuestionFields from '@/components/assessment/QuestionFields.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    copyFromBank,
    ensureIds,
    hasQuestionErrors,
    newQuestion,
    questionErrors,
    questionsFrom,
    quizPayload,
} from '@/lib/quizForm';
import { copyable } from '@/routes/assessment/questions';
import type { StepData, StepErrors, WizardActivity } from '@/types/activities';
import type { BankQuestion } from '@/types/assessment';
import type { QuizQuestionForm } from '@/types/quiz';

/*
 * UC-022: the questions step of a quiz in the activity wizard (rendered by
 * Activities/Wizard). The teacher writes questions of the five types or
 * copies questions from their bank (DATA-008: the copy is the quiz's own),
 * and orders them by drag and drop. Every change goes into the step data
 * that the wizard autosaves.
 */

const props = defineProps<{
    modelValue: StepData;
    errors: StepErrors;
    activity: WizardActivity | null;
    locked: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: StepData];
}>();

const questions = ref<QuizQuestionForm[]>(questionsFrom(props.modelValue));
const open = ref<Record<string, boolean>>({});

const bankOpen = ref(false);
const bankLoading = ref(false);
const bankFailed = ref(false);
const bank = ref<BankQuestion[]>([]);
const copied = ref<Record<string, number>>({});

watch(
    questions,
    (list) => {
        list.forEach((question) => ensureIds(question));
        emit('update:modelValue', quizPayload(list));
    },
    { deep: true, immediate: true },
);

function isOpen(question: QuizQuestionForm, index: number): boolean {
    return open.value[question.id] ?? hasQuestionErrors(props.errors, index);
}

function toggle(question: QuizQuestionForm, index: number): void {
    open.value = { ...open.value, [question.id]: !isOpen(question, index) };
}

function addQuestion(): void {
    const question = newQuestion();
    questions.value = [...questions.value, question];
    open.value = { ...open.value, [question.id]: true };
}

function removeQuestion(index: number): void {
    questions.value = questions.value.filter((_, i) => i !== index);
}

async function toggleBank(): Promise<void> {
    bankOpen.value = !bankOpen.value;

    if (!bankOpen.value || bank.value.length > 0) {
        return;
    }

    bankLoading.value = true;
    bankFailed.value = false;

    try {
        const response = await fetch(copyable().url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        bank.value = (
            (await response.json()) as { questions: BankQuestion[] }
        ).questions;
    } catch {
        bankFailed.value = true;
    } finally {
        bankLoading.value = false;
    }
}

function copy(question: BankQuestion): void {
    questions.value = [...questions.value, copyFromBank(question)];
    copied.value = {
        ...copied.value,
        [question.id]: (copied.value[question.id] ?? 0) + 1,
    };
}
</script>

<template>
    <div class="flex flex-col gap-6" data-test="quiz-questions">
        <p class="text-sm text-muted-foreground">
            {{ $t('assessment.quiz.questions.hint') }}
        </p>

        <p
            v-if="questions.length === 0"
            class="text-sm"
            data-test="quiz-questions-empty"
        >
            {{ $t('assessment.quiz.questions.empty') }}
        </p>

        <VueDraggable
            v-model="questions"
            tag="ol"
            handle="[data-question-handle]"
            :animation="150"
            class="flex flex-col gap-3"
            data-test="quiz-question-list"
        >
            <li
                v-for="(question, index) in questions"
                :key="question.id"
                class="rounded-md border p-3"
                data-test="quiz-question"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        data-question-handle
                        class="cursor-grab text-muted-foreground"
                        :aria-label="$t('assessment.form.drag')"
                        :title="$t('assessment.form.drag')"
                        :data-test="`quiz-question-handle-${index}`"
                    >
                        <GripVertical class="size-4" />
                    </button>
                    <span class="text-sm font-medium">{{ index + 1 }}.</span>
                    <span
                        class="flex-1 truncate text-sm"
                        :data-test="`quiz-question-title-${index}`"
                    >
                        {{
                            question.prompt ||
                            $t('assessment.quiz.questions.untitled')
                        }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        {{ $t(`assessment.types.${question.type}`) }} ·
                        {{
                            $t('assessment.quiz.questions.points', {
                                points: String(question.points),
                            })
                        }}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="
                            isOpen(question, index)
                                ? $t('assessment.quiz.questions.collapse')
                                : $t('assessment.quiz.questions.expand')
                        "
                        :data-test="`quiz-question-toggle-${index}`"
                        @click="toggle(question, index)"
                    >
                        <ChevronUp
                            v-if="isOpen(question, index)"
                            class="size-4"
                        />
                        <ChevronDown v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('assessment.quiz.questions.remove')"
                        :data-test="`quiz-question-remove-${index}`"
                        @click="removeQuestion(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <InputError
                    v-if="!isOpen(question, index)"
                    :message="
                        hasQuestionErrors(errors, index)
                            ? $t('assessment.quiz.questions.has_errors')
                            : undefined
                    "
                />
                <div
                    v-if="isOpen(question, index)"
                    class="mt-4 flex flex-col gap-6"
                    :data-test="`quiz-question-fields-${index}`"
                >
                    <QuestionFields
                        v-model:form="questions[index]"
                        :errors="questionErrors(errors, index)"
                        :id-prefix="`q-${question.id}-`"
                    />
                </div>
            </li>
        </VueDraggable>
        <InputError :message="errors.questions" />

        <div class="flex flex-wrap gap-3">
            <Button
                type="button"
                variant="outline"
                data-test="quiz-add-question"
                @click="addQuestion"
            >
                <Plus class="size-4" />
                {{ $t('assessment.quiz.questions.add') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                :aria-expanded="bankOpen"
                data-test="quiz-open-bank"
                @click="toggleBank"
            >
                <Library class="size-4" />
                {{ $t('assessment.quiz.questions.from_bank') }}
            </Button>
        </div>

        <section
            v-if="bankOpen"
            class="flex flex-col gap-3 rounded-md border p-3"
            data-test="quiz-bank"
        >
            <h2 class="text-sm font-medium">
                {{ $t('assessment.quiz.bank.title') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{ $t('assessment.quiz.bank.hint') }}
            </p>
            <Spinner v-if="bankLoading" />
            <p
                v-else-if="bankFailed"
                class="text-sm text-red-600 dark:text-red-500"
                role="alert"
            >
                {{ $t('assessment.quiz.bank.failed') }}
            </p>
            <p v-else-if="bank.length === 0" class="text-sm">
                {{ $t('assessment.quiz.bank.empty') }}
            </p>
            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="item in bank"
                    :key="item.id"
                    class="flex items-center gap-2"
                    data-test="quiz-bank-question"
                >
                    <span class="flex-1 text-sm">{{ item.prompt }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ $t(`assessment.types.${item.type}`) }}
                    </span>
                    <span
                        v-if="copied[item.id]"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('assessment.quiz.bank.added') }}
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        data-test="quiz-bank-copy"
                        @click="copy(item)"
                    >
                        <Plus class="size-4" />
                        {{ $t('assessment.quiz.bank.copy') }}
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
