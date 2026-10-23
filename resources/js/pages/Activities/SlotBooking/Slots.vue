<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    blankSlot,
    durationFrom,
    slotsFrom,
    slotsPayload,
    withEnd,
    withStart,
} from '@/lib/slotForm';
import type { StepData, StepErrors, WizardActivity } from '@/types/activities';
import type { SlotInput } from '@/types/slots';
import {
    MAX_SLOT_CAPACITY,
    MAX_SLOT_MINUTES,
    MAX_SLOTS,
    MIN_SLOT_MINUTES,
    MIN_SLOTS,
} from '@/types/slots';

/*
 * UC-016: the content step of a slot booking in the activity wizard: the
 * optional slot length and the slots with the number of places each one
 * has (SCOPE-028). The slot length only fills in the end of a new slot
 * from its start; the end can still be changed. Once slots are booked, a
 * booked slot cannot be removed and its places cannot drop below its
 * bookings (the server refuses that).
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

const minutes = ref<number | string>(durationFrom(props.modelValue) ?? '');
const slots = ref<SlotInput[]>(slotsFrom(props.modelValue));

watch(
    [slots, minutes],
    () =>
        emit('update:modelValue', slotsPayload(slots.value, currentMinutes())),
    { deep: true, immediate: true },
);

function currentMinutes(): number | null {
    return String(minutes.value).trim() === '' ||
        Number.isNaN(Number(minutes.value))
        ? null
        : Number(minutes.value);
}

function setStart(index: number, value: string): void {
    slots.value = slots.value.map((slot, i) =>
        i === index ? withStart(slot, value, currentMinutes()) : slot,
    );
}

function setEnd(index: number, value: string): void {
    slots.value = slots.value.map((slot, i) =>
        i === index ? withEnd(slot, value) : slot,
    );
}

function addSlot(): void {
    slots.value = [...slots.value, blankSlot()];
}

function removeSlot(index: number): void {
    slots.value = slots.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="flex flex-col gap-4" data-test="slot-editor">
        <div class="flex flex-col gap-1">
            <h3 class="text-sm font-medium">
                {{ $t('activities.slots.editor.title') }}
            </h3>
            <p class="text-xs text-muted-foreground">
                {{ $t('activities.slots.editor.hint') }}
            </p>
        </div>

        <Alert v-if="locked">
            <AlertDescription>{{
                $t('activities.slots.editor.booked_notice')
            }}</AlertDescription>
        </Alert>

        <div class="grid max-w-xs gap-2">
            <Label for="slot-duration">{{
                $t('activities.slots.editor.duration')
            }}</Label>
            <Input
                id="slot-duration"
                v-model.number="minutes"
                type="number"
                :min="MIN_SLOT_MINUTES"
                :max="MAX_SLOT_MINUTES"
                data-test="slot-duration"
            />
            <p class="text-xs text-muted-foreground">
                {{ $t('activities.slots.editor.duration_hint') }}
            </p>
            <InputError :message="errors.slot_duration" />
        </div>

        <div
            v-for="(slot, index) in slots"
            :key="slot.id"
            class="flex flex-col gap-3 rounded-md border p-3"
            :data-test="`slot-${index}`"
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium">{{
                    $t('activities.slots.editor.slot_title', {
                        number: String(index + 1),
                    })
                }}</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="slots.length <= MIN_SLOTS"
                    :aria-label="$t('activities.slots.editor.remove')"
                    :data-test="`slot-remove-${index}`"
                    @click="removeSlot(index)"
                >
                    <X class="size-4" />
                </Button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`slot-start-${index}`">{{
                        $t('activities.slots.editor.starts_at')
                    }}</Label>
                    <Input
                        :id="`slot-start-${index}`"
                        :model-value="slot.starts_at"
                        type="datetime-local"
                        :data-test="`slot-start-${index}`"
                        @update:model-value="setStart(index, String($event))"
                    />
                    <InputError :message="errors[`slots.${index}.starts_at`]" />
                </div>
                <div class="grid gap-2">
                    <Label :for="`slot-end-${index}`">{{
                        $t('activities.slots.editor.ends_at')
                    }}</Label>
                    <Input
                        :id="`slot-end-${index}`"
                        :model-value="slot.ends_at"
                        type="datetime-local"
                        :data-test="`slot-end-${index}`"
                        @update:model-value="setEnd(index, String($event))"
                    />
                    <InputError :message="errors[`slots.${index}.ends_at`]" />
                </div>
                <div class="grid gap-2">
                    <Label :for="`slot-location-${index}`">{{
                        $t('activities.slots.editor.location')
                    }}</Label>
                    <Input
                        :id="`slot-location-${index}`"
                        v-model="slot.location"
                        maxlength="255"
                        :data-test="`slot-location-${index}`"
                    />
                    <InputError :message="errors[`slots.${index}.location`]" />
                </div>
                <div class="grid gap-2">
                    <Label :for="`slot-capacity-${index}`">{{
                        $t('activities.slots.editor.capacity')
                    }}</Label>
                    <Input
                        :id="`slot-capacity-${index}`"
                        v-model.number="slot.capacity"
                        type="number"
                        min="1"
                        :max="MAX_SLOT_CAPACITY"
                        :data-test="`slot-capacity-${index}`"
                    />
                    <InputError :message="errors[`slots.${index}.capacity`]" />
                </div>
            </div>
            <InputError :message="errors[`slots.${index}.id`]" />
        </div>

        <InputError :message="errors.slots" />

        <div>
            <Button
                type="button"
                variant="outline"
                :disabled="slots.length >= MAX_SLOTS"
                data-test="slot-add"
                @click="addSlot"
            >
                <Plus class="size-4" />
                {{ $t('activities.slots.editor.add') }}
            </Button>
        </div>
    </div>
</template>
