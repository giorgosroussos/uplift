<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { gradeLabel, passLabel } from '@/lib/grade';
import { index, show } from '@/routes/assessment/results';
import type { ActivityResults } from '@/types/results';

/*
 * UC-017: My results. Per activity the student's submitted attempts with
 * the grade on the organisation's grading scale (when the quiz shows it),
 * provisional or final, and whether the teacher left feedback.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assessment.results.index.title', href: index() },
        ],
    },
});

defineProps<{
    results: ActivityResults[];
}>();

function formatDate(value: string | null): string {
    return value === null
        ? ''
        : new Date(value).toLocaleString(document.documentElement.lang, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}
</script>

<template>
    <Head :title="$t('assessment.results.index.title')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">
            {{ $t('assessment.results.index.title') }}
        </h1>

        <p
            v-if="results.length === 0"
            class="text-sm text-muted-foreground"
            data-test="results-empty"
        >
            {{ $t('assessment.results.index.empty') }}
        </p>

        <section
            v-for="activity in results"
            :key="activity.activity_id"
            class="flex flex-col gap-3 rounded-lg border p-4"
            data-test="result-activity"
        >
            <div class="flex flex-col gap-0.5">
                <h2 class="font-medium">{{ activity.title }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ activity.course_space_title }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 pr-4 font-medium" />
                            <th class="py-2 pr-4 font-medium">
                                {{
                                    $t('assessment.results.index.submitted_at')
                                }}
                            </th>
                            <th class="py-2 pr-4 font-medium">
                                {{ $t('assessment.results.index.grade') }}
                            </th>
                            <th class="py-2 pr-4 font-medium">
                                {{ $t('assessment.results.index.status') }}
                            </th>
                            <th class="py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="attempt in activity.attempts"
                            :key="attempt.id"
                            class="border-b last:border-0"
                            data-test="result-attempt"
                        >
                            <td class="py-2 pr-4">
                                {{
                                    $t('assessment.results.index.attempt', {
                                        number: String(attempt.number),
                                    })
                                }}
                            </td>
                            <td class="py-2 pr-4">
                                {{ formatDate(attempt.submitted_at) }}
                            </td>
                            <td class="py-2 pr-4" data-test="result-grade">
                                <template v-if="attempt.score">
                                    {{ gradeLabel(attempt.score.grade, $t) }}
                                    <span
                                        v-if="
                                            passLabel(attempt.score.grade, $t)
                                        "
                                        class="text-muted-foreground"
                                    >
                                        ·
                                        {{ passLabel(attempt.score.grade, $t) }}
                                    </span>
                                </template>
                                <span v-else class="text-muted-foreground">
                                    {{ $t('assessment.grade.hidden') }}
                                </span>
                            </td>
                            <td class="py-2 pr-4">
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        :variant="
                                            attempt.final
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                        data-test="result-status"
                                    >
                                        {{
                                            attempt.final
                                                ? $t('assessment.grade.final')
                                                : $t(
                                                      'assessment.grade.provisional',
                                                  )
                                        }}
                                    </Badge>
                                    <Badge
                                        v-if="attempt.has_feedback"
                                        variant="outline"
                                        data-test="result-feedback"
                                    >
                                        {{
                                            $t(
                                                'assessment.results.index.has_feedback',
                                            )
                                        }}
                                    </Badge>
                                </div>
                            </td>
                            <td class="py-2 text-right">
                                <Link
                                    :href="show(attempt.id)"
                                    class="underline"
                                    data-test="result-open"
                                >
                                    {{ $t('assessment.results.index.open') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
