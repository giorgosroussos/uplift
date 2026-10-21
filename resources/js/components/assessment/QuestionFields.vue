<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { GripVertical, Plus, X } from '@lucide/vue';
import { computed, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    blankOption,
    blankPair,
    hasOptions,
    hasPairs,
    hasSingleCorrect,
    itemsForType,
} from '@/lib/questionForm';
import type { QuestionForm } from '@/types/assessment';
import { MAX_ITEMS, MIN_ITEMS, QUESTION_TYPES } from '@/types/assessment';

/*
 * UC-023: the question builder, shared by the create and edit pages: type,
 * prompt, points, and the options or matching pairs ordered by drag and
 * drop (DATA-016). True/false options keep their stored texts 'true' and
 * 'false' and are shown through translation keys.
 */
const form = defineModel<InertiaForm<QuestionForm>>('form', {
    required: true,
});

const errors = computed(() => form.value.errors as Record<string, string>);
const isTrueFalse = computed(() => form.value.type === 'true_false');

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

watch(
    () => form.value.type,
    (type) => {
        const items = itemsForType(type, form.value.options, form.value.pairs);
        form.value.options = items.options;
        form.value.pairs = items.pairs;
    },
);

function markCorrect(index: number, checked: boolean): void {
    if (hasSingleCorrect(form.value.type)) {
        form.value.options = form.value.options.map((option, i) => ({
            ...option,
            is_correct: i === index,
        }));

        return;
    }

    form.value.options[index].is_correct = checked;
}

function addOption(): void {
    form.value.options = [...form.value.options, blankOption()];
}

function removeOption(index: number): void {
    form.value.options = form.value.options.filter((_, i) => i !== index);
}

function addPair(): void {
    form.value.pairs = [...form.value.pairs, blankPair()];
}

function removePair(index: number): void {
    form.value.pairs = form.value.pairs.filter((_, i) => i !== index);
}

function optionsHint(): string {
    if (form.value.type === 'true_false') {
        return 'assessment.form.options_hint_true_false';
    }

    return form.value.type === 'single_choice'
        ? 'assessment.form.options_hint_single'
        : 'assessment.form.options_hint_multiple';
}
</script>

<template>
    <fieldset class="grid gap-2">
        <legend class="mb-2 text-sm font-medium">
            {{ $t('assessment.form.type') }}
        </legend>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <label
                v-for="type in QUESTION_TYPES"
                :key="type"
                class="flex items-center gap-2 text-sm"
            >
                <input
                    v-model="form.type"
                    type="radio"
                    name="type"
                    :value="type"
                    :data-test="`question-type-${type}`"
                />
                {{ $t(`assessment.types.${type}`) }}
            </label>
        </div>
        <InputError :message="form.errors.type" />
    </fieldset>

    <div class="grid gap-2">
        <Label for="prompt">{{ $t('assessment.form.prompt') }}</Label>
        <textarea
            id="prompt"
            v-model="form.prompt"
            name="prompt"
            rows="3"
            required
            :class="textareaClass"
            data-test="question-prompt"
        />
        <InputError :message="form.errors.prompt" />
    </div>

    <div class="grid max-w-48 gap-2">
        <Label for="points">{{ $t('assessment.form.points') }}</Label>
        <Input
            id="points"
            v-model="form.points"
            name="points"
            type="number"
            min="0.01"
            max="100"
            step="0.01"
            required
            data-test="question-points"
        />
        <p class="text-xs text-muted-foreground">
            {{ $t('assessment.form.points_hint') }}
        </p>
    </div>
    <InputError :message="form.errors.points" />

    <section v-if="hasOptions(form.type)" class="grid gap-3">
        <div>
            <h2 class="text-sm font-medium">
                {{ $t('assessment.form.options') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                <template v-if="!isTrueFalse">
                    {{ $t('assessment.form.options_hint') }}
                </template>
                {{ $t(optionsHint()) }}
            </p>
        </div>

        <VueDraggable
            v-model="form.options"
            tag="ul"
            handle="[data-drag-handle]"
            :animation="150"
            class="grid gap-2"
            data-test="options-list"
        >
            <li
                v-for="(option, index) in form.options"
                :key="option.key"
                class="grid gap-1"
                data-test="option"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        data-drag-handle
                        class="cursor-grab text-muted-foreground"
                        :aria-label="$t('assessment.form.drag')"
                        :title="$t('assessment.form.drag')"
                        :data-test="`option-handle-${index}`"
                    >
                        <GripVertical class="size-4" />
                    </button>
                    <label class="flex items-center gap-1 text-sm">
                        <input
                            :type="
                                hasSingleCorrect(form.type)
                                    ? 'radio'
                                    : 'checkbox'
                            "
                            name="correct"
                            :checked="option.is_correct"
                            :data-test="`option-correct-${index}`"
                            @change="
                                markCorrect(
                                    index,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span class="sr-only md:not-sr-only">
                            {{ $t('assessment.form.correct') }}
                        </span>
                    </label>
                    <span
                        v-if="isTrueFalse"
                        class="text-sm"
                        :data-test="`option-label-${index}`"
                    >
                        {{ $t(`assessment.true_false.${option.text}`) }}
                    </span>
                    <template v-else>
                        <Input
                            v-model="option.text"
                            :aria-label="
                                $t('assessment.form.option_text', {
                                    number: String(index + 1),
                                })
                            "
                            :placeholder="
                                $t('assessment.form.option_text', {
                                    number: String(index + 1),
                                })
                            "
                            maxlength="1000"
                            class="flex-1"
                            :data-test="`option-text-${index}`"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="form.options.length <= MIN_ITEMS"
                            :aria-label="$t('assessment.form.remove')"
                            :data-test="`option-remove-${index}`"
                            @click="removeOption(index)"
                        >
                            <X class="size-4" />
                        </Button>
                    </template>
                </div>
                <InputError :message="errors[`options.${index}.text`]" />
            </li>
        </VueDraggable>

        <div v-if="!isTrueFalse">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="form.options.length >= MAX_ITEMS"
                data-test="add-option"
                @click="addOption"
            >
                <Plus class="size-4" />
                {{ $t('assessment.form.add_option') }}
            </Button>
        </div>
        <InputError :message="form.errors.options" />
    </section>

    <section v-else-if="hasPairs(form.type)" class="grid gap-3">
        <div>
            <h2 class="text-sm font-medium">
                {{ $t('assessment.form.pairs') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{ $t('assessment.form.pairs_hint') }}
            </p>
        </div>

        <VueDraggable
            v-model="form.pairs"
            tag="ul"
            handle="[data-drag-handle]"
            :animation="150"
            class="grid gap-2"
            data-test="pairs-list"
        >
            <li
                v-for="(pair, index) in form.pairs"
                :key="pair.key"
                class="grid gap-1"
                data-test="pair"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        data-drag-handle
                        class="cursor-grab text-muted-foreground"
                        :aria-label="$t('assessment.form.drag')"
                        :title="$t('assessment.form.drag')"
                        :data-test="`pair-handle-${index}`"
                    >
                        <GripVertical class="size-4" />
                    </button>
                    <Input
                        v-model="pair.left_text"
                        :aria-label="
                            $t('assessment.form.left_text', {
                                number: String(index + 1),
                            })
                        "
                        :placeholder="
                            $t('assessment.form.left_text', {
                                number: String(index + 1),
                            })
                        "
                        maxlength="1000"
                        class="flex-1"
                        :data-test="`pair-left-${index}`"
                    />
                    <Input
                        v-model="pair.right_text"
                        :aria-label="
                            $t('assessment.form.right_text', {
                                number: String(index + 1),
                            })
                        "
                        :placeholder="
                            $t('assessment.form.right_text', {
                                number: String(index + 1),
                            })
                        "
                        maxlength="1000"
                        class="flex-1"
                        :data-test="`pair-right-${index}`"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="form.pairs.length <= MIN_ITEMS"
                        :aria-label="$t('assessment.form.remove')"
                        :data-test="`pair-remove-${index}`"
                        @click="removePair(index)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
                <InputError :message="errors[`pairs.${index}.left_text`]" />
                <InputError :message="errors[`pairs.${index}.right_text`]" />
            </li>
        </VueDraggable>

        <div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="form.pairs.length >= MAX_ITEMS"
                data-test="add-pair"
                @click="addPair"
            >
                <Plus class="size-4" />
                {{ $t('assessment.form.add_pair') }}
            </Button>
        </div>
        <InputError :message="form.errors.pairs" />
    </section>

    <p v-else class="text-sm text-muted-foreground">
        {{ $t('assessment.form.free_text_hint') }}
    </p>
</template>
