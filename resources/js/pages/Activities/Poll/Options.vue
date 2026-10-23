<script setup lang="ts">
import { GripVertical, Plus, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { blankPollOption, pollOptionsFrom } from '@/lib/pollForm';
import type { StepData, StepErrors, WizardActivity } from '@/types/activities';
import type { PollOptionInput } from '@/types/polls';
import { MAX_POLL_OPTIONS, MIN_POLL_OPTIONS } from '@/types/polls';

/*
 * UC-013: the content step of a poll in the activity wizard: the options,
 * ordered by drag and drop. Once students have voted, the options can
 * only be reordered (the server refuses other changes too).
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

const options = ref<PollOptionInput[]>(pollOptionsFrom(props.modelValue));

watch(
    options,
    () =>
        emit('update:modelValue', {
            options: options.value.map((option) => ({ ...option })),
        }),
    { deep: true, immediate: true },
);

function addOption(): void {
    options.value = [...options.value, blankPollOption()];
}

function removeOption(index: number): void {
    options.value = options.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="flex flex-col gap-4" data-test="poll-options">
        <div class="flex flex-col gap-1">
            <h3 class="text-sm font-medium">
                {{ $t('activities.poll.options.title') }}
            </h3>
            <p class="text-xs text-muted-foreground">
                {{
                    $t('activities.poll.options.hint', {
                        min: String(MIN_POLL_OPTIONS),
                        max: String(MAX_POLL_OPTIONS),
                    })
                }}
            </p>
        </div>

        <Alert v-if="locked">
            <AlertDescription>{{
                $t('activities.poll.options.locked')
            }}</AlertDescription>
        </Alert>

        <VueDraggable
            v-model="options"
            handle="[data-drag-handle]"
            class="flex flex-col gap-2"
        >
            <div
                v-for="(option, index) in options"
                :key="option.id"
                class="flex flex-col gap-1"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="cursor-grab text-muted-foreground"
                        data-drag-handle
                        :data-test="`poll-option-handle-${index}`"
                        :aria-label="$t('activities.poll.options.reorder')"
                    >
                        <GripVertical class="size-4" />
                    </button>
                    <Input
                        v-model="option.label"
                        :aria-label="
                            $t('activities.poll.options.label', {
                                number: String(index + 1),
                            })
                        "
                        :placeholder="
                            $t('activities.poll.options.label', {
                                number: String(index + 1),
                            })
                        "
                        maxlength="255"
                        :disabled="locked"
                        :data-test="`poll-option-label-${index}`"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="locked || options.length <= MIN_POLL_OPTIONS"
                        :aria-label="$t('activities.poll.options.remove')"
                        :data-test="`poll-option-remove-${index}`"
                        @click="removeOption(index)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
                <InputError :message="errors[`options.${index}.label`]" />
                <InputError :message="errors[`options.${index}.id`]" />
            </div>
        </VueDraggable>

        <InputError :message="errors.options" />

        <div>
            <Button
                type="button"
                variant="outline"
                :disabled="locked || options.length >= MAX_POLL_OPTIONS"
                data-test="poll-option-add"
                @click="addOption"
            >
                <Plus class="size-4" />
                {{ $t('activities.poll.options.add') }}
            </Button>
        </div>
    </div>
</template>
