<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CourseSpaceForm } from '@/types/courses';

/*
 * Title, description and enrolment policy of a course space (UC-021),
 * shared by the create and edit pages.
 */
const form = defineModel<InertiaForm<CourseSpaceForm>>('form', {
    required: true,
});
</script>

<template>
    <div class="grid gap-2">
        <Label for="title">{{ $t('courses.form.title') }}</Label>
        <Input
            id="title"
            v-model="form.title"
            name="title"
            required
            maxlength="255"
            data-test="course-title"
        />
        <InputError :message="form.errors.title" />
    </div>

    <div class="grid gap-2">
        <Label for="description">{{ $t('courses.form.description') }}</Label>
        <textarea
            id="description"
            v-model="form.description"
            name="description"
            rows="5"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
            data-test="course-description"
        />
        <InputError :message="form.errors.description" />
    </div>

    <fieldset class="grid gap-2">
        <legend class="mb-2 text-sm font-medium">
            {{ $t('courses.form.enrolment_policy') }}
        </legend>
        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="form.enrolment_policy"
                type="radio"
                name="enrolment_policy"
                value="open"
                data-test="policy-open"
            />
            {{ $t('courses.form.policy_open') }}
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="form.enrolment_policy"
                type="radio"
                name="enrolment_policy"
                value="key"
                data-test="policy-key"
            />
            {{ $t('courses.form.policy_key') }}
        </label>
        <InputError :message="form.errors.enrolment_policy" />
    </fieldset>

    <div v-if="form.enrolment_policy === 'key'" class="grid gap-2">
        <Label for="enrolment_key">{{
            $t('courses.form.enrolment_key')
        }}</Label>
        <Input
            id="enrolment_key"
            v-model="form.enrolment_key"
            name="enrolment_key"
            required
            maxlength="255"
            autocomplete="off"
            data-test="course-enrolment-key"
        />
        <InputError :message="form.errors.enrolment_key" />
    </div>
</template>
