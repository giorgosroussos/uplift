<script setup lang="ts">
import { reactive, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pollSettingsFrom } from '@/lib/pollForm';
import type { StepData, StepErrors, WizardActivity } from '@/types/activities';
import { MAX_POLL_OPTIONS, POLL_RESULTS_VISIBILITIES } from '@/types/polls';

/*
 * UC-013: the settings step of a poll in the activity wizard (rendered by
 * Activities/Wizard): how many options a student may choose and when
 * students see the results (SCOPE-013). Once students have voted, the
 * number of choices is locked (the server refuses it too).
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

const settings = reactive(pollSettingsFrom(props.modelValue));

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-50 dark:bg-input/30';

watch(settings, () => emit('update:modelValue', { ...settings }), {
    deep: true,
    immediate: true,
});
</script>

<template>
    <div class="flex flex-col gap-6" data-test="poll-settings">
        <div class="grid max-w-60 gap-2">
            <Label for="poll-max-choices">{{
                $t('activities.poll.settings.max_choices')
            }}</Label>
            <Input
                id="poll-max-choices"
                :model-value="settings.max_choices"
                type="number"
                min="1"
                :max="MAX_POLL_OPTIONS"
                step="1"
                required
                :disabled="locked"
                data-test="poll-max-choices"
                @update:model-value="settings.max_choices = Number($event)"
            />
            <p class="text-xs text-muted-foreground">
                {{ $t('activities.poll.settings.max_choices_hint') }}
            </p>
            <InputError :message="errors.max_choices" />
        </div>

        <div class="grid gap-2">
            <Label for="poll-results-visibility">{{
                $t('activities.poll.settings.results_visibility')
            }}</Label>
            <select
                id="poll-results-visibility"
                v-model="settings.results_visibility"
                :class="selectClass"
                data-test="poll-results-visibility"
            >
                <option
                    v-for="visibility in POLL_RESULTS_VISIBILITIES"
                    :key="visibility"
                    :value="visibility"
                >
                    {{ $t(`activities.poll.visibility.${visibility}`) }}
                </option>
            </select>
            <InputError :message="errors.results_visibility" />
        </div>
    </div>
</template>
