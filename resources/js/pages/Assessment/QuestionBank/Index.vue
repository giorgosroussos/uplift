<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import { create as createImport } from '@/routes/assessment/imports';
import { create, destroy, edit, index } from '@/routes/assessment/questions';
import type { BankQuestionSummary } from '@/types/assessment';
import type { Paginated } from '@/types/courses';

/*
 * UC-023: the teacher's own question bank, newest first.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'assessment.nav.question_bank', href: index() }],
    },
});

defineProps<{
    questions: Paginated<BankQuestionSummary>;
}>();

const deletingId = ref<string | null>(null);

function confirmDelete(): void {
    if (deletingId.value === null) {
        return;
    }

    router.visit(destroy(deletingId.value), {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="$t('assessment.index.title')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-medium">
                {{ $t('assessment.index.heading') }}
            </h1>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="createImport()" data-test="import-questions">
                        {{ $t('assessment.index.import') }}
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="create()" data-test="create-question">
                        {{ $t('assessment.index.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="questions.data.length === 0"
            class="text-sm text-muted-foreground"
            data-test="questions-empty"
        >
            {{ $t('assessment.index.empty') }}
        </p>

        <ul v-else class="grid gap-3">
            <li
                v-for="question in questions.data"
                :key="question.id"
                class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                data-test="bank-question"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                    <Link
                        :href="edit(question.id)"
                        class="line-clamp-2 font-medium break-words hover:underline"
                        data-test="bank-question-link"
                    >
                        {{ question.prompt }}
                    </Link>
                    <div
                        class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Badge variant="secondary">
                            {{ $t(`assessment.types.${question.type}`) }}
                        </Badge>
                        <span>
                            {{ $t('assessment.index.points') }}:
                            {{ question.points }}
                        </span>
                        <span v-if="question.items_count > 0">
                            {{ $t('assessment.index.items') }}:
                            {{ question.items_count }}
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="edit(question.id)">
                            {{ $t('assessment.index.edit') }}
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        data-test="bank-question-delete"
                        @click="deletingId = question.id"
                    >
                        {{ $t('assessment.index.delete') }}
                    </Button>
                </div>
            </li>
        </ul>

        <nav
            v-if="questions.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <Link
                v-if="questions.prev_page_url"
                :href="questions.prev_page_url"
                class="underline"
            >
                {{ $t('assessment.index.previous') }}
            </Link>
            <span v-else />
            <span
                >{{ questions.current_page }} / {{ questions.last_page }}</span
            >
            <Link
                v-if="questions.next_page_url"
                :href="questions.next_page_url"
                class="underline"
            >
                {{ $t('assessment.index.next') }}
            </Link>
            <span v-else />
        </nav>

        <Dialog
            :open="deletingId !== null"
            @update:open="(open: boolean) => !open && (deletingId = null)"
        >
            <DialogContent data-test="delete-question-dialog">
                <DialogTitle>
                    {{ $t('assessment.index.confirm_delete') }}
                </DialogTitle>
                <DialogDescription>
                    {{ $t('assessment.index.confirm_delete_body') }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('assessment.index.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        data-test="delete-question-confirm"
                        @click="confirmDelete"
                    >
                        {{ $t('assessment.index.delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
