<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/courses/announcements';
import type { Announcement, AnnouncementForm } from '@/types/courses';

/*
 * UC-008: the announcements of a course space, newest first. The owning
 * teacher posts, edits and deletes them; enrolled students read them.
 */
const props = defineProps<{
    spaceId: string;
    announcements: Announcement[];
    canManage: boolean;
}>();

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

const postForm = useForm<AnnouncementForm>({ title: '', body: '' });
const editForm = useForm<AnnouncementForm>({ title: '', body: '' });
const editingId = ref<string | null>(null);
const deletingId = ref<string | null>(null);

function post(): void {
    postForm.submit(store(props.spaceId), {
        preserveScroll: true,
        onSuccess: () => postForm.reset(),
    });
}

function startEdit(announcement: Announcement): void {
    editingId.value = announcement.id;
    editForm.clearErrors();
    editForm.title = announcement.title;
    editForm.body = announcement.body;
}

function saveEdit(): void {
    if (editingId.value === null) {
        return;
    }

    editForm.submit(
        update({ courseSpace: props.spaceId, announcement: editingId.value }),
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
}

function confirmDelete(): void {
    if (deletingId.value === null) {
        return;
    }

    router.visit(
        destroy({ courseSpace: props.spaceId, announcement: deletingId.value }),
        {
            preserveScroll: true,
            onFinish: () => {
                deletingId.value = null;
            },
        },
    );
}

function formatDate(value: string | null): string {
    return value === null
        ? ''
        : new Date(value).toLocaleString(
              document.documentElement.lang || undefined,
          );
}
</script>

<template>
    <section class="flex flex-col gap-4" data-test="announcements">
        <h2 class="text-lg font-medium">
            {{ $t('courses.announcements.heading') }}
        </h2>

        <form
            v-if="canManage"
            class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            data-test="announcement-form"
            @submit.prevent="post"
        >
            <h3 class="font-medium">{{ $t('courses.announcements.new') }}</h3>
            <div class="grid gap-2">
                <Label for="announcement_title">{{
                    $t('courses.announcements.title')
                }}</Label>
                <Input
                    id="announcement_title"
                    v-model="postForm.title"
                    name="title"
                    required
                    maxlength="255"
                    data-test="announcement-title"
                />
                <InputError :message="postForm.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="announcement_body">{{
                    $t('courses.announcements.body')
                }}</Label>
                <textarea
                    id="announcement_body"
                    v-model="postForm.body"
                    name="body"
                    rows="4"
                    required
                    :class="textareaClass"
                    data-test="announcement-body"
                />
                <InputError :message="postForm.errors.body" />
            </div>
            <div>
                <Button
                    type="submit"
                    :disabled="postForm.processing"
                    data-test="announcement-submit"
                >
                    {{ $t('courses.announcements.post') }}
                </Button>
            </div>
        </form>

        <p
            v-if="announcements.length === 0"
            class="text-sm text-muted-foreground"
            data-test="announcements-empty"
        >
            {{ $t('courses.announcements.empty') }}
        </p>

        <article
            v-for="announcement in announcements"
            :key="announcement.id"
            class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            data-test="announcement"
        >
            <form
                v-if="editingId === announcement.id"
                class="flex flex-col gap-4"
                @submit.prevent="saveEdit"
            >
                <div class="grid gap-2">
                    <Label :for="`edit_title_${announcement.id}`">{{
                        $t('courses.announcements.title')
                    }}</Label>
                    <Input
                        :id="`edit_title_${announcement.id}`"
                        v-model="editForm.title"
                        name="title"
                        required
                        maxlength="255"
                    />
                    <InputError :message="editForm.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label :for="`edit_body_${announcement.id}`">{{
                        $t('courses.announcements.body')
                    }}</Label>
                    <textarea
                        :id="`edit_body_${announcement.id}`"
                        v-model="editForm.body"
                        name="body"
                        rows="4"
                        required
                        :class="textareaClass"
                    />
                    <InputError :message="editForm.errors.body" />
                </div>
                <div class="flex gap-2">
                    <Button
                        type="submit"
                        :disabled="editForm.processing"
                        data-test="announcement-save"
                    >
                        {{ $t('courses.announcements.save') }}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="editingId = null"
                    >
                        {{ $t('courses.announcements.cancel') }}
                    </Button>
                </div>
            </form>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h3 class="font-medium" data-test="announcement-heading">
                        {{ announcement.title }}
                    </h3>
                    <div v-if="canManage" class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            data-test="announcement-edit"
                            @click="startEdit(announcement)"
                        >
                            {{ $t('courses.announcements.edit') }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            data-test="announcement-delete"
                            @click="deletingId = announcement.id"
                        >
                            {{ $t('courses.announcements.delete') }}
                        </Button>
                    </div>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('courses.announcements.published_by', {
                            author: announcement.author,
                            date: formatDate(announcement.published_at),
                        })
                    }}
                </p>
                <p class="whitespace-pre-line" data-test="announcement-text">
                    {{ announcement.body }}
                </p>
            </template>
        </article>

        <Dialog
            :open="deletingId !== null"
            @update:open="(open: boolean) => !open && (deletingId = null)"
        >
            <DialogContent>
                <DialogTitle>
                    {{ $t('courses.announcements.confirm_delete') }}
                </DialogTitle>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('courses.announcements.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        data-test="announcement-delete-confirm"
                        @click="confirmDelete"
                    >
                        {{ $t('courses.announcements.delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
