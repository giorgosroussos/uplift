<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CourseSpaceFields from '@/components/courses/CourseSpaceFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { create, index, store } from '@/routes/courses';
import type { CourseSpaceForm } from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'courses.nav.courses', href: index() },
            { title: 'courses.create.title', href: create() },
        ],
    },
});

const form = useForm<CourseSpaceForm>({
    title: '',
    description: '',
    enrolment_policy: 'open',
    enrolment_key: '',
});

function submit(): void {
    form.submit(store());
}
</script>

<template>
    <Head :title="$t('courses.create.title')" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">{{ $t('courses.create.title') }}</h1>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <CourseSpaceFields v-model:form="form" />

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="course-submit"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('courses.create.submit') }}
                </Button>
                <Link :href="index()" class="text-sm underline">
                    {{ $t('courses.form.cancel') }}
                </Link>
            </div>
        </form>
    </div>
</template>
