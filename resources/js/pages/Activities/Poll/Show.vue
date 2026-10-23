<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { toggleChoice, votePercentages } from '@/lib/pollForm';
import { edit, index, show as showActivity } from '@/routes/activities';
import { vote as voteRoute } from '@/routes/activities/polls';
import type { PollDetails, PollResults } from '@/types/polls';

/*
 * UC-013: a poll as a student (vote once, then the results as the teacher
 * set them) or its teacher (the results at any time) sees it. Results are
 * counts only: nobody sees who voted or what anyone voted (SEC-009).
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    poll: PollDetails;
    hasVoted: boolean;
    results: PollResults | null;
    can: { vote: boolean; update: boolean };
}>();

const page = usePage();
const refusal = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.options,
);
const chosen = ref<string[]>([]);
const submitting = ref(false);
const multiple = computed(() => props.poll.max_choices > 1);
const percentages = computed(() =>
    props.results ? votePercentages(props.results) : {},
);

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function toggle(id: string): void {
    chosen.value = toggleChoice(chosen.value, id, props.poll.max_choices);
}

function submit(): void {
    submitting.value = true;
    router.post(
        voteRoute(props.poll.activity_id).url,
        { options: chosen.value },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="poll.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="poll-title">
                    {{ poll.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{{
                        $t('activities.types.poll')
                    }}</Badge>
                    <Badge variant="outline" data-test="poll-status">
                        {{ $t(`activities.statuses.${poll.status}`) }}
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="showActivity(poll.activity_id)">
                        {{ $t('activities.wizard.back') }}
                    </Link>
                </Button>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(poll.activity_id)">
                        {{ $t('activities.show.edit') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="poll.description"
            class="text-sm whitespace-pre-line"
            data-test="poll-description"
        >
            {{ poll.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('activities.show.course_space') }}
            </dt>
            <dd>{{ poll.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('activities.show.deadline') }}
            </dt>
            <dd>
                {{
                    poll.deadline_at
                        ? formatDate(poll.deadline_at)
                        : $t('activities.show.no_deadline')
                }}
            </dd>
        </dl>

        <p class="text-sm text-muted-foreground">
            {{ $t('activities.poll.show.anonymous') }}
        </p>

        <Alert v-if="!poll.is_open && poll.status !== 'draft'">
            <AlertDescription>{{
                $t('activities.poll.show.closed')
            }}</AlertDescription>
        </Alert>
        <Alert v-if="hasVoted" data-test="poll-voted">
            <AlertDescription>{{
                $t('activities.poll.show.voted')
            }}</AlertDescription>
        </Alert>

        <form
            v-if="can.vote"
            class="flex flex-col gap-4"
            data-test="poll-vote-form"
            @submit.prevent="submit"
        >
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{
                        multiple
                            ? $t('activities.poll.show.choose_up_to', {
                                  max: String(poll.max_choices),
                              })
                            : $t('activities.poll.show.choose_one')
                    }}
                </legend>
                <label
                    v-for="(option, position) in poll.options"
                    :key="option.id"
                    class="flex items-center gap-2 rounded-md border p-3 text-sm"
                >
                    <input
                        :type="multiple ? 'checkbox' : 'radio'"
                        name="poll-option"
                        :value="option.id"
                        :checked="chosen.includes(option.id)"
                        :disabled="
                            multiple &&
                            !chosen.includes(option.id) &&
                            chosen.length >= poll.max_choices
                        "
                        :data-test="`poll-choice-${position + 1}`"
                        @change="toggle(option.id)"
                    />
                    {{ option.label }}
                </label>
            </fieldset>
            <InputError :message="refusal" />
            <p class="text-xs text-muted-foreground">
                {{ $t('activities.poll.show.final') }}
            </p>
            <div>
                <Button
                    type="submit"
                    :disabled="submitting || chosen.length === 0"
                    data-test="poll-submit"
                >
                    {{ $t('activities.poll.show.submit') }}
                </Button>
            </div>
        </form>
        <InputError v-else :message="refusal" />

        <section
            v-if="results"
            class="flex flex-col gap-3"
            data-test="poll-results"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-medium">
                    {{ $t('activities.poll.show.results') }}
                </h2>
                <span
                    class="text-sm text-muted-foreground"
                    data-test="poll-participants"
                >
                    {{
                        $tChoice(
                            'activities.poll.show.participants',
                            results.participants,
                        )
                    }}
                </span>
            </div>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="(option, position) in results.options"
                    :key="option.id"
                    class="flex flex-col gap-1"
                    :data-test="`poll-result-${position + 1}`"
                >
                    <div class="flex justify-between gap-2 text-sm">
                        <span>{{ option.label }}</span>
                        <span class="text-muted-foreground">
                            {{
                                $tChoice(
                                    'activities.poll.show.votes',
                                    option.votes,
                                )
                            }}
                            ({{ percentages[option.id] ?? 0 }}%)
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-muted">
                        <div
                            class="h-2 rounded-full bg-primary"
                            :style="{
                                width: `${percentages[option.id] ?? 0}%`,
                            }"
                        />
                    </div>
                </li>
            </ul>
        </section>
        <p
            v-else-if="poll.status !== 'draft'"
            class="text-sm text-muted-foreground"
            data-test="poll-results-hidden"
        >
            {{
                poll.results_visibility === 'after_close' || hasVoted
                    ? $t('activities.poll.show.results_after_close')
                    : $t('activities.poll.show.results_after_vote')
            }}
        </p>
    </div>
</template>
