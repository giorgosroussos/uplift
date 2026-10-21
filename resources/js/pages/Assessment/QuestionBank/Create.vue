<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import QuestionFields from '@/components/assessment/QuestionFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { emptyQuestionForm, questionPayload } from '@/lib/questionForm';
import { create, index, store } from '@/routes/assessment/questions';
import type { QuestionForm } from '@/types/assessment';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assessment.nav.question_bank', href: index() },
            { title: 'assessment.create.title', href: create() },
        ],
    },
});

const form = useForm<QuestionForm>(emptyQuestionForm());

function submit(): void {
    form.transform(questionPayload).submit(store());
}
</script>

<template>
    <Head :title="$t('assessment.create.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">
            {{ $t('assessment.create.title') }}
        </h1>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <QuestionFields v-model:form="form" />

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="question-submit"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('assessment.create.submit') }}
                </Button>
                <Link :href="index()" class="text-sm underline">
                    {{ $t('assessment.form.cancel') }}
                </Link>
            </div>
        </form>
    </div>
</template>
