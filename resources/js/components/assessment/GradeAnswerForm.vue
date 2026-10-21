<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/assessment/grading';
import type { ReviewQuestion } from '@/types/results';

/**
 * UC-026 step 2: points from 0 to the question's maximum and optional
 * feedback for one answer. The server validates and recalculates the
 * attempt's score.
 */
const props = defineProps<{
    question: ReviewQuestion & { answer_id: string };
    number: number;
}>();

const form = useForm({
    points: props.question.points_awarded ?? '',
    feedback: props.question.feedback ?? '',
});

function submit(): void {
    form.put(update.url(props.question.answer_id), { preserveScroll: true });
}
</script>

<template>
    <form
        class="flex flex-col gap-3 border-t pt-3"
        :data-test="`grade-form-${number}`"
        @submit.prevent="submit"
    >
        <div class="flex flex-col gap-1">
            <Label :for="`points-${question.id}`">
                {{ $t('assessment.grading.points') }}
            </Label>
            <Input
                :id="`points-${question.id}`"
                v-model="form.points"
                class="max-w-32"
                inputmode="decimal"
                :aria-describedby="`points-hint-${question.id}`"
                :data-test="`grade-points-${number}`"
            />
            <p
                :id="`points-hint-${question.id}`"
                class="text-xs text-muted-foreground"
            >
                {{
                    $t('assessment.grading.points_hint', {
                        max: question.points,
                    })
                }}
            </p>
            <InputError :message="form.errors.points" />
        </div>
        <div class="flex flex-col gap-1">
            <Label :for="`feedback-${question.id}`">
                {{ $t('assessment.grading.feedback') }}
            </Label>
            <textarea
                :id="`feedback-${question.id}`"
                v-model="form.feedback"
                rows="3"
                maxlength="5000"
                class="rounded-md border bg-background p-2 text-sm"
                :data-test="`grade-feedback-${number}`"
            />
            <InputError :message="form.errors.feedback" />
        </div>
        <div>
            <Button
                type="submit"
                size="sm"
                :disabled="form.processing"
                :data-test="`grade-save-${number}`"
            >
                {{ $t('assessment.grading.save') }}
            </Button>
        </div>
    </form>
</template>
