<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CourseAnnouncements from '@/components/courses/CourseAnnouncements.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { archive, edit, enrol, index, restore } from '@/routes/courses';
import { destroy, store as addStudent } from '@/routes/courses/students';
import type {
    Announcement,
    CourseSpaceDetails,
    EnrolledStudent,
    StudentOption,
} from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'courses.nav.courses', href: index() }],
    },
});

const props = defineProps<{
    space: CourseSpaceDetails;
    can: {
        update: boolean;
        enrol: boolean;
        manageEnrolments: boolean;
        manageAnnouncements: boolean;
    };
    enrolled: boolean;
    announcements: Announcement[];
    enrolmentsCount: number | null;
    students: EnrolledStudent[];
    availableStudents: StudentOption[];
}>();

const page = usePage();
const statusError = computed(
    () => (page.props.errors as Record<string, string>).status,
);

const enrolForm = useForm({ enrolment_key: '' });
const addForm = useForm({ student_id: '' });

function submitEnrolment(): void {
    enrolForm.submit(enrol(props.space.id), {
        preserveScroll: true,
        onFinish: () => enrolForm.reset('enrolment_key'),
    });
}

function submitStudent(): void {
    addForm.submit(addStudent(props.space.id), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}

function removeStudent(enrolmentId: string): void {
    router.visit(
        destroy({ courseSpace: props.space.id, enrolment: enrolmentId }),
        { preserveScroll: true },
    );
}

// DATA-012: archiving unenrols every student, so the teacher confirms it.
const confirmingArchive = ref(false);

function changeStatus(): void {
    if (props.space.status === 'active') {
        confirmingArchive.value = true;

        return;
    }

    router.visit(restore(props.space.id), { preserveScroll: true });
}

function confirmArchive(): void {
    router.visit(archive(props.space.id), {
        preserveScroll: true,
        onFinish: () => {
            confirmingArchive.value = false;
        },
    });
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(
        document.documentElement.lang || undefined,
    );
}
</script>

<template>
    <Head :title="space.title" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="course-title">
                    {{ space.title }}
                </h1>
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span
                        >{{ $t('courses.show.teacher') }}:
                        {{ space.teacher }}</span
                    >
                    <Badge
                        :variant="
                            space.status === 'active' ? 'secondary' : 'outline'
                        "
                        data-test="course-status"
                    >
                        {{ $t(`courses.show.status_${space.status}`) }}
                    </Badge>
                    <Badge variant="outline">
                        {{
                            $t(`courses.show.policy_${space.enrolment_policy}`)
                        }}
                    </Badge>
                </div>
            </div>

            <div v-if="can.update" class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="edit(space.id)" data-test="course-edit">
                        {{ $t('courses.show.edit') }}
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    data-test="course-status-toggle"
                    @click="changeStatus"
                >
                    {{
                        space.status === 'active'
                            ? $t('courses.show.archive')
                            : $t('courses.show.restore')
                    }}
                </Button>
            </div>
        </div>

        <InputError :message="statusError" />

        <p v-if="space.description" class="whitespace-pre-line">
            {{ space.description }}
        </p>

        <p
            v-if="can.update && space.enrolment_key"
            class="text-sm"
            data-test="course-enrolment-key"
        >
            {{ $t('courses.show.enrolment_key') }}:
            <code class="rounded bg-muted px-1 py-0.5">{{
                space.enrolment_key
            }}</code>
        </p>

        <p
            v-if="space.status === 'archived'"
            role="status"
            class="text-sm text-muted-foreground"
            data-test="archived-notice"
        >
            {{ $t('courses.show.archived_notice') }}
        </p>

        <p
            v-if="enrolled"
            role="status"
            class="text-sm font-medium text-green-600"
            data-test="enrolled"
        >
            {{ $t('courses.show.enrolled') }}
        </p>

        <form
            v-else-if="can.enrol"
            class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            data-test="enrol-form"
            @submit.prevent="submitEnrolment"
        >
            <div
                v-if="space.enrolment_policy === 'key'"
                class="grid max-w-sm gap-2"
            >
                <Label for="enrolment_key">{{
                    $t('courses.show.enrolment_key')
                }}</Label>
                <Input
                    id="enrolment_key"
                    v-model="enrolForm.enrolment_key"
                    name="enrolment_key"
                    required
                    autocomplete="off"
                    data-test="enrolment-key"
                />
                <p class="text-sm text-muted-foreground">
                    {{ $t('courses.show.enrol_key_hint') }}
                </p>
            </div>
            <InputError :message="enrolForm.errors.enrolment_key" />
            <InputError
                :message="
                    (enrolForm.errors as Record<string, string>).enrolment
                "
            />
            <div>
                <Button
                    type="submit"
                    :disabled="enrolForm.processing"
                    data-test="enrol-button"
                >
                    {{ $t('courses.show.enrol') }}
                </Button>
            </div>
        </form>

        <p
            v-if="enrolmentsCount !== null && !can.manageEnrolments"
            class="text-sm"
            data-test="enrolments-count"
        >
            {{ $t('courses.show.enrolments_count') }}: {{ enrolmentsCount }}
        </p>

        <CourseAnnouncements
            v-if="can.manageAnnouncements || enrolled"
            :space-id="space.id"
            :announcements="announcements"
            :can-manage="can.manageAnnouncements"
        />

        <section v-if="can.manageEnrolments" class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">
                {{ $t('courses.show.students') }} ({{ students.length }})
            </h2>

            <p
                v-if="students.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('courses.show.no_students') }}
            </p>

            <table v-else class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">
                            {{ $t('courses.show.student_name') }}
                        </th>
                        <th class="py-2">
                            {{ $t('courses.show.student_email') }}
                        </th>
                        <th class="py-2">
                            {{ $t('courses.show.enrolled_at') }}
                        </th>
                        <th class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="student in students"
                        :key="student.enrolment_id"
                        class="border-b"
                        data-test="enrolled-student"
                    >
                        <td class="py-2">{{ student.name }}</td>
                        <td class="py-2">{{ student.email }}</td>
                        <td class="py-2">
                            {{ formatDate(student.enrolled_at) }}
                        </td>
                        <td class="py-2 text-right">
                            <Button
                                variant="ghost"
                                size="sm"
                                data-test="remove-student"
                                @click="removeStudent(student.enrolment_id)"
                            >
                                {{ $t('courses.show.remove') }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <form
                v-if="space.status === 'active'"
                class="flex flex-wrap items-end gap-4"
                @submit.prevent="submitStudent"
            >
                <div class="grid gap-2">
                    <Label for="student_id">{{
                        $t('courses.show.add_student')
                    }}</Label>
                    <select
                        id="student_id"
                        v-model="addForm.student_id"
                        required
                        :disabled="availableStudents.length === 0"
                        class="h-9 min-w-64 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                        data-test="add-student-select"
                    >
                        <option value="" disabled>
                            {{
                                availableStudents.length === 0
                                    ? $t('courses.show.no_available_students')
                                    : $t('courses.show.choose_student')
                            }}
                        </option>
                        <option
                            v-for="student in availableStudents"
                            :key="student.id"
                            :value="student.id"
                        >
                            {{ student.name }} ({{ student.email }})
                        </option>
                    </select>
                    <InputError :message="addForm.errors.student_id" />
                </div>
                <Button
                    type="submit"
                    :disabled="addForm.processing || addForm.student_id === ''"
                    data-test="add-student"
                >
                    {{ $t('courses.show.add') }}
                </Button>
            </form>
        </section>

        <Dialog v-model:open="confirmingArchive">
            <DialogContent data-test="archive-dialog">
                <DialogTitle>
                    {{ $t('courses.show.confirm_archive_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t('courses.show.confirm_archive_text', {
                            count: String(students.length),
                        })
                    }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('courses.show.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        data-test="archive-confirm"
                        @click="confirmArchive"
                    >
                        {{ $t('courses.show.confirm_archive') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
