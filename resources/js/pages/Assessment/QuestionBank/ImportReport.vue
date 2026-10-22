<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { confirm, create, destroy } from '@/routes/assessment/imports';
import { index } from '@/routes/assessment/questions';
import type { QuestionImportReport } from '@/types/assessment';
import { QUESTION_TYPES } from '@/types/assessment';

/*
 * UC-030 and DATA-017: the report of an uploaded question CSV, every row
 * with its line number and 'valid' or its errors. Confirm imports the
 * valid rows (disabled when there are none); cancel deletes the file.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assessment.nav.question_bank', href: index() },
            { title: 'assessment.import.title', href: create() },
        ],
    },
});

const props = defineProps<{
    report: QuestionImportReport;
}>();

const page = usePage();
const processing = ref(false);

const canConfirm = computed(
    () => props.report.refusal === null && props.report.valid_count > 0,
);

const confirmError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.import,
);

function isKnownType(type: string): boolean {
    return (QUESTION_TYPES as readonly string[]).includes(type);
}

function submitConfirm(): void {
    router.visit(confirm(props.report.id), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

function submitCancel(): void {
    router.visit(destroy(props.report.id), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Head :title="$t('assessment.import.report.title')" />

    <div class="flex flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">
            {{ $t('assessment.import.report.title') }}
        </h1>

        <Alert
            v-if="report.refusal !== null"
            variant="destructive"
            data-test="import-refusal"
        >
            <AlertTitle>{{
                $t('assessment.import.report.refused')
            }}</AlertTitle>
            <AlertDescription>{{ report.refusal }}</AlertDescription>
        </Alert>

        <template v-else>
            <p class="text-sm" data-test="import-summary">
                {{
                    $t('assessment.import.report.summary', {
                        valid: String(report.valid_count),
                        invalid: String(report.invalid_count),
                    })
                }}
            </p>
            <p
                v-if="report.valid_count === 0"
                class="text-sm text-destructive"
                data-test="import-none-valid"
            >
                {{ $t('assessment.import.report.none_valid') }}
            </p>
            <p
                v-else-if="report.invalid_count > 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('assessment.import.report.skipped_notice') }}
            </p>

            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('assessment.import.report.line') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('assessment.import.report.type') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('assessment.import.report.question') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('assessment.import.report.result') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.rows"
                            :key="row.line"
                            class="border-b align-top last:border-0"
                            :data-line="row.line"
                            data-test="import-row"
                        >
                            <td class="px-3 py-2 tabular-nums">
                                {{ row.line }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{
                                    isKnownType(row.type)
                                        ? $t(`assessment.types.${row.type}`)
                                        : $t(
                                              'assessment.import.report.unknown_type',
                                          )
                                }}
                            </td>
                            <td class="max-w-md px-3 py-2 break-words">
                                <span class="line-clamp-2">{{
                                    row.prompt
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    v-if="row.valid"
                                    variant="secondary"
                                    data-test="import-row-valid"
                                >
                                    {{ $t('assessment.import.report.valid') }}
                                </Badge>
                                <ul
                                    v-else
                                    class="list-disc space-y-1 pl-4 text-destructive"
                                    data-test="import-row-errors"
                                >
                                    <li
                                        v-for="(error, i) in row.errors"
                                        :key="i"
                                    >
                                        {{ error }}
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <InputError :message="confirmError" />

        <div class="flex flex-wrap items-center gap-4">
            <Button
                :disabled="!canConfirm || processing"
                data-test="import-confirm"
                @click="submitConfirm"
            >
                <Spinner v-if="processing" />
                {{
                    $t('assessment.import.report.confirm', {
                        count: String(report.valid_count),
                    })
                }}
            </Button>
            <Button
                variant="outline"
                :disabled="processing"
                data-test="import-cancel"
                @click="submitCancel"
            >
                {{ $t('assessment.import.report.cancel') }}
            </Button>
        </div>
    </div>
</template>
