<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import QuestionFields from '@/components/assessment/QuestionFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { questionFormFrom, questionPayload } from '@/lib/questionForm';
import { index, update } from '@/routes/assessment/questions';
import type { BankQuestion, QuestionForm } from '@/types/assessment';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assessment.nav.question_bank', href: index() },
            { title: 'assessment.edit.title', href: index() },
        ],
    },
});

const props = defineProps<{
    question: BankQuestion;
}>();

const form = useForm<QuestionForm>(questionFormFrom(props.question));

function submit(): void {
    form.transform(questionPayload).submit(update(props.question.id));
}
</script>

<template>
    <Head :title="$t('assessment.edit.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">{{ $t('assessment.edit.title') }}</h1>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <QuestionFields v-model:form="form" :errors="form.errors" />

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="question-submit"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('assessment.edit.submit') }}
                </Button>
                <Link :href="index()" class="text-sm underline">
                    {{ $t('assessment.form.cancel') }}
                </Link>
            </div>
        </form>
    </div>
</template>
