<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CourseSpaceFields from '@/components/courses/CourseSpaceFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, show, update } from '@/routes/courses';
import type { CourseSpaceForm, EnrolmentPolicy } from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'courses.nav.courses', href: index() },
            { title: 'courses.edit.title', href: index() },
        ],
    },
});

const props = defineProps<{
    space: {
        id: string;
        title: string;
        description: string | null;
        enrolment_policy: EnrolmentPolicy;
        enrolment_key: string | null;
    };
}>();

const form = useForm<CourseSpaceForm>({
    title: props.space.title,
    description: props.space.description ?? '',
    enrolment_policy: props.space.enrolment_policy,
    enrolment_key: props.space.enrolment_key ?? '',
});

function submit(): void {
    form.submit(update(props.space.id));
}
</script>

<template>
    <Head :title="$t('courses.edit.title')" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">{{ $t('courses.edit.title') }}</h1>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <CourseSpaceFields v-model:form="form" />

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="course-submit"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('courses.edit.submit') }}
                </Button>
                <Link :href="show(space.id)" class="text-sm underline">
                    {{ $t('courses.form.cancel') }}
                </Link>
            </div>
        </form>
    </div>
</template>
