<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, store, template } from '@/routes/assessment/imports';
import { index } from '@/routes/assessment/questions';

/*
 * UC-030 and DATA-017: upload a question CSV into the teacher's own bank.
 * The next page shows the per-row report before anything is imported.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assessment.nav.question_bank', href: index() },
            { title: 'assessment.import.title', href: create() },
        ],
    },
});

const FORMAT_KEYS = [
    'encoding',
    'columns',
    'type',
    'choice',
    'true_false',
    'matching',
    'free_text',
    'limits',
] as const;

const form = useForm<{ file: File | null }>({ file: null });

function chooseFile(event: Event): void {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    form.submit(store());
}
</script>

<template>
    <Head :title="$t('assessment.import.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">
            {{ $t('assessment.import.heading') }}
        </h1>

        <p class="text-sm text-muted-foreground">
            {{ $t('assessment.import.intro') }}
        </p>

        <div class="flex flex-col gap-2">
            <div>
                <Button variant="outline" as-child>
                    <a
                        :href="template().url"
                        download
                        data-test="import-template"
                    >
                        {{ $t('assessment.import.template') }}
                    </a>
                </Button>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ $t('assessment.import.template_hint') }}
            </p>
        </div>

        <section class="flex flex-col gap-2">
            <h2 class="font-medium">
                {{ $t('assessment.import.format_heading') }}
            </h2>
            <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                <li v-for="key in FORMAT_KEYS" :key="key">
                    {{ $t(`assessment.import.format.${key}`) }}
                </li>
            </ul>
        </section>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="file">{{ $t('assessment.import.file') }}</Label>
                <input
                    id="file"
                    type="file"
                    name="file"
                    accept=".csv,text/csv"
                    class="text-sm"
                    data-test="import-file"
                    @change="chooseFile"
                />
                <InputError
                    :message="form.errors.file"
                    data-test="import-file-error"
                />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing || form.file === null"
                    data-test="import-upload"
                >
                    <Spinner v-if="form.processing" />
                    {{ $t('assessment.import.upload') }}
                </Button>
                <Link :href="index()" class="text-sm underline">
                    {{ $t('assessment.import.cancel') }}
                </Link>
            </div>
        </form>
    </div>
</template>
