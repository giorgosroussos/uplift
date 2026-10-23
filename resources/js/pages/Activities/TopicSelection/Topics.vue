<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { blankTopic, topicsFrom, topicsPayload } from '@/lib/topicForm';
import type { StepData, StepErrors, WizardActivity } from '@/types/activities';
import type { TopicInput } from '@/types/topics';
import { MAX_TOPIC_CAPACITY, MAX_TOPICS, MIN_TOPICS } from '@/types/topics';

/*
 * UC-014: the content step of a topic selection in the activity wizard:
 * the topics with the number of places each one has (SCOPE-011). Once
 * students have chosen, a chosen topic cannot be removed and its places
 * cannot drop below its holders (the server refuses that).
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

const topics = ref<TopicInput[]>(topicsFrom(props.modelValue));

watch(
    topics,
    () => emit('update:modelValue', { topics: topicsPayload(topics.value) }),
    { deep: true, immediate: true },
);

function addTopic(): void {
    topics.value = [...topics.value, blankTopic()];
}

function removeTopic(index: number): void {
    topics.value = topics.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="flex flex-col gap-4" data-test="topic-editor">
        <div class="flex flex-col gap-1">
            <h3 class="text-sm font-medium">
                {{ $t('activities.topics.editor.title') }}
            </h3>
            <p class="text-xs text-muted-foreground">
                {{ $t('activities.topics.editor.hint') }}
            </p>
        </div>

        <Alert v-if="locked">
            <AlertDescription>{{
                $t('activities.topics.editor.held_notice')
            }}</AlertDescription>
        </Alert>

        <div
            v-for="(topic, index) in topics"
            :key="topic.id"
            class="flex flex-col gap-3 rounded-md border p-3"
            :data-test="`topic-${index}`"
        >
            <div class="flex items-start gap-2">
                <div class="grid flex-1 gap-2">
                    <Label :for="`topic-title-${index}`">{{
                        $t('activities.topics.editor.topic_title', {
                            number: String(index + 1),
                        })
                    }}</Label>
                    <Input
                        :id="`topic-title-${index}`"
                        v-model="topic.title"
                        maxlength="255"
                        :data-test="`topic-title-${index}`"
                    />
                    <InputError :message="errors[`topics.${index}.title`]" />
                    <InputError :message="errors[`topics.${index}.id`]" />
                </div>
                <div class="grid w-28 gap-2">
                    <Label :for="`topic-capacity-${index}`">{{
                        $t('activities.topics.editor.capacity')
                    }}</Label>
                    <Input
                        :id="`topic-capacity-${index}`"
                        v-model.number="topic.capacity"
                        type="number"
                        min="1"
                        :max="MAX_TOPIC_CAPACITY"
                        :data-test="`topic-capacity-${index}`"
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="mt-6"
                    :disabled="topics.length <= MIN_TOPICS"
                    :aria-label="$t('activities.topics.editor.remove')"
                    :data-test="`topic-remove-${index}`"
                    @click="removeTopic(index)"
                >
                    <X class="size-4" />
                </Button>
            </div>
            <InputError :message="errors[`topics.${index}.capacity`]" />
            <div class="grid gap-2">
                <Label :for="`topic-description-${index}`">{{
                    $t('activities.topics.editor.description')
                }}</Label>
                <textarea
                    :id="`topic-description-${index}`"
                    v-model="topic.description"
                    rows="2"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    :data-test="`topic-description-${index}`"
                />
                <InputError :message="errors[`topics.${index}.description`]" />
            </div>
        </div>

        <InputError :message="errors.topics" />

        <div>
            <Button
                type="button"
                variant="outline"
                :disabled="topics.length >= MAX_TOPICS"
                data-test="topic-add"
                @click="addTopic"
            >
                <Plus class="size-4" />
                {{ $t('activities.topics.editor.add') }}
            </Button>
        </div>
    </div>
</template>
