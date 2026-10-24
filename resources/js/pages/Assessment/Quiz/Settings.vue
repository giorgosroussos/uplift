<script setup lang="ts">
import { reactive, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fromLocalInput, toLocalInput } from '@/lib/activityWizard';
import { settingsFrom, settingsPayload } from '@/lib/quizForm';
import type { StepData, StepErrors, WizardActivity } from '@/types/activities';
import {
    LIVE_START_MODES,
    QUIZ_MODES,
    RESULT_VISIBILITIES,
} from '@/types/quiz';

/*
 * UC-022: the settings step of a quiz in the activity wizard (rendered by
 * Activities/Wizard). The teacher chooses a self-paced or a live quiz
 * (SCOPE-016). A live quiz (SCOPE-021) has a duration and starts manually
 * from a lobby or at a scheduled time; it has no attempts, negative
 * marking, pass mark or result visibility. Once the quiz has submissions,
 * the mode and the settings that change grading are locked (the server
 * refuses them too).
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

const settings = reactive(settingsFrom(props.modelValue));

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-50 dark:bg-input/30';

function emitSettings(): void {
    emit('update:modelValue', settingsPayload(settings));
}

watch(settings, emitSettings, { deep: true, immediate: true });

function numberOrNull(value: string | number): number | null {
    return value === '' || value === null ? null : Number(value);
}

function stringOrNull(value: string | number): string | null {
    return value === '' || value === null ? null : String(value);
}
</script>

<template>
    <div class="flex flex-col gap-6" data-test="quiz-settings">
        <div class="grid gap-2">
            <Label for="quiz-mode">{{
                $t('assessment.quiz.settings.mode')
            }}</Label>
            <select
                id="quiz-mode"
                v-model="settings.mode"
                :class="selectClass"
                class="max-w-60"
                :disabled="locked"
                data-test="quiz-mode"
            >
                <option v-for="mode in QUIZ_MODES" :key="mode" :value="mode">
                    {{ $t(`assessment.quiz.modes.${mode}`) }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground" data-test="quiz-mode-hint">
                {{ $t(`assessment.quiz.settings.mode_hints.${settings.mode}`) }}
            </p>
            <InputError :message="errors.mode" />
        </div>

        <fieldset
            v-if="settings.mode === 'live'"
            class="grid gap-3"
            data-test="quiz-live-start"
        >
            <legend class="mb-2 text-sm font-medium">
                {{ $t('assessment.quiz.settings.live_start_mode') }}
            </legend>
            <label
                v-for="startMode in LIVE_START_MODES"
                :key="startMode"
                class="flex items-center gap-2 text-sm"
            >
                <input
                    :checked="
                        (settings.live_start_mode ?? 'manual') === startMode
                    "
                    type="radio"
                    name="quiz-live-start-mode"
                    :value="startMode"
                    :data-test="`quiz-live-start-${startMode}`"
                    @change="settings.live_start_mode = startMode"
                />
                {{
                    $t(`assessment.quiz.settings.live_start_modes.${startMode}`)
                }}
            </label>
            <InputError :message="errors.live_start_mode" />

            <div
                v-if="settings.live_start_mode === 'scheduled'"
                class="grid max-w-60 gap-2"
            >
                <Label for="quiz-live-start-at">{{
                    $t('assessment.quiz.settings.live_scheduled_start_at')
                }}</Label>
                <Input
                    id="quiz-live-start-at"
                    :model-value="
                        toLocalInput(settings.live_scheduled_start_at)
                    "
                    type="datetime-local"
                    data-test="quiz-live-start-at"
                    @update:model-value="
                        settings.live_scheduled_start_at = fromLocalInput(
                            String($event),
                        )
                    "
                />
                <InputError :message="errors.live_scheduled_start_at" />
            </div>
        </fieldset>

        <div class="grid max-w-60 gap-2">
            <Label for="quiz-time-limit">{{
                settings.mode === 'live'
                    ? $t('assessment.quiz.settings.duration')
                    : $t('assessment.quiz.settings.time_limit')
            }}</Label>
            <Input
                id="quiz-time-limit"
                :model-value="settings.time_limit_minutes ?? ''"
                type="number"
                min="1"
                max="1440"
                step="1"
                :required="settings.mode === 'live'"
                data-test="quiz-time-limit"
                @update:model-value="
                    settings.time_limit_minutes = numberOrNull($event)
                "
            />
            <p class="text-xs text-muted-foreground">
                {{
                    settings.mode === 'live'
                        ? $t('assessment.quiz.settings.duration_hint')
                        : $t('assessment.quiz.settings.time_limit_hint')
                }}
            </p>
            <InputError :message="errors.time_limit_minutes" />
        </div>

        <template v-if="settings.mode === 'self_paced'">
            <div class="grid max-w-60 gap-2">
                <Label for="quiz-max-attempts">{{
                    $t('assessment.quiz.settings.max_attempts')
                }}</Label>
                <Input
                    id="quiz-max-attempts"
                    :model-value="settings.max_attempts"
                    type="number"
                    min="1"
                    max="100"
                    step="1"
                    required
                    data-test="quiz-max-attempts"
                    @update:model-value="
                        settings.max_attempts = numberOrNull($event) ?? 0
                    "
                />
                <InputError :message="errors.max_attempts" />
            </div>

            <fieldset class="grid gap-3" :disabled="locked">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="settings.negative_marking"
                        type="checkbox"
                        data-test="quiz-negative-marking"
                    />
                    {{ $t('assessment.quiz.settings.negative_marking') }}
                </label>
                <InputError :message="errors.negative_marking" />

                <div
                    v-if="settings.negative_marking"
                    class="grid max-w-60 gap-2"
                >
                    <Label for="quiz-negative-mark">{{
                        $t('assessment.quiz.settings.negative_mark_fraction')
                    }}</Label>
                    <Input
                        id="quiz-negative-mark"
                        :model-value="settings.negative_mark_fraction ?? ''"
                        type="number"
                        min="0.01"
                        max="100"
                        step="0.01"
                        data-test="quiz-negative-mark"
                        @update:model-value="
                            settings.negative_mark_fraction =
                                stringOrNull($event)
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('assessment.quiz.settings.negative_mark_hint') }}
                    </p>
                    <InputError :message="errors.negative_mark_fraction" />
                </div>

                <div class="grid max-w-60 gap-2">
                    <Label for="quiz-pass-mark">{{
                        $t('assessment.quiz.settings.pass_mark')
                    }}</Label>
                    <Input
                        id="quiz-pass-mark"
                        :model-value="settings.pass_mark_percentage ?? ''"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        data-test="quiz-pass-mark"
                        @update:model-value="
                            settings.pass_mark_percentage = stringOrNull($event)
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('assessment.quiz.settings.pass_mark_hint') }}
                    </p>
                    <InputError :message="errors.pass_mark_percentage" />
                </div>
            </fieldset>

            <div class="grid gap-2">
                <Label for="quiz-score-visibility">{{
                    $t('assessment.quiz.settings.score_visibility')
                }}</Label>
                <select
                    id="quiz-score-visibility"
                    v-model="settings.score_visibility"
                    :class="selectClass"
                    data-test="quiz-score-visibility"
                >
                    <option
                        v-for="visibility in RESULT_VISIBILITIES"
                        :key="visibility"
                        :value="visibility"
                    >
                        {{ $t(`assessment.quiz.visibility.${visibility}`) }}
                    </option>
                </select>
                <InputError :message="errors.score_visibility" />
            </div>

            <div class="grid gap-2">
                <Label for="quiz-answers-visibility">{{
                    $t('assessment.quiz.settings.answers_visibility')
                }}</Label>
                <select
                    id="quiz-answers-visibility"
                    v-model="settings.answers_visibility"
                    :class="selectClass"
                    data-test="quiz-answers-visibility"
                >
                    <option
                        v-for="visibility in RESULT_VISIBILITIES"
                        :key="visibility"
                        :value="visibility"
                    >
                        {{ $t(`assessment.quiz.visibility.${visibility}`) }}
                    </option>
                </select>
                <InputError :message="errors.answers_visibility" />
            </div>
        </template>
    </div>
</template>
