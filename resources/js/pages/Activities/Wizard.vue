<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import type { Component } from 'vue';
import { computed, defineAsyncComponent, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    fromLocalInput,
    problemErrors,
    readyForReview,
    sendJson,
    toLocalInput,
} from '@/lib/activityWizard';
import { edit, index, publish, show, store } from '@/routes/activities';
import { update as saveStep } from '@/routes/activities/wizard';
import type {
    ActivityKind,
    CourseSpaceOption,
    StepData,
    StepErrors,
    WizardActivity,
    WizardBasics,
    WizardStep,
    WizardStepKey,
} from '@/types/activities';

/*
 * UC-022: the multi-step activity wizard. Every step is autosaved when the
 * teacher moves on; returning restores all saved steps from the server.
 * The settings and content steps are Vue pages of the activity's type
 * (ActivityType::component on the server).
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    activity: WizardActivity | null;
    basics: WizardBasics;
    data: { settings: StepData | null; content: StepData | null };
    steps: WizardStep[];
    step: WizardStepKey;
    gradingLocked: boolean;
    types: ActivityKind[];
    courseSpaces: CourseSpaceOption[];
    can: { publish: boolean; close: boolean };
}>();

const steps = ref<WizardStep[]>(props.steps);
const current = ref<WizardStepKey>(props.step);
const errors = ref<StepErrors>({});
const saveState = ref<'idle' | 'saving' | 'saved' | 'failed'>('idle');
const publishing = ref(false);

const basics = reactive({
    course_space_id: props.basics.course_space_id ?? '',
    type: props.basics.type ?? (props.types.length === 1 ? props.types[0] : ''),
    title: props.basics.title,
    description: props.basics.description ?? '',
    deadline: toLocalInput(props.basics.deadline_at),
});

const stepData = reactive<Record<'settings' | 'content', StepData>>({
    settings: props.data.settings ?? {},
    content: props.data.content ?? {},
});

const isNew = computed(() => props.activity === null);
const currentIndex = computed(() =>
    steps.value.findIndex((step) => step.key === current.value),
);
const canCreate = computed(
    () => props.types.length > 0 && props.courseSpaces.length > 0,
);

// Vue pages of the type-owned steps, loaded on demand. The glob is rooted
// at the project, so its keys are the same for pages inside this
// directory (e.g. Activities/Poll/Settings) and outside it.
const pages = import.meta.glob<{ default: Component }>(
    '/resources/js/pages/**/*.vue',
);
const stepComponents = computed<Record<string, Component | null>>(() => {
    const resolved: Record<string, Component | null> = {};

    for (const step of steps.value) {
        if (step.component) {
            const loader = pages[`/resources/js/pages/${step.component}.vue`];
            resolved[step.key] = loader ? defineAsyncComponent(loader) : null;
        }
    }

    return resolved;
});

function basicsPayload(): Record<string, unknown> {
    return {
        course_space_id: basics.course_space_id,
        type: basics.type,
        title: basics.title,
        description: basics.description === '' ? null : basics.description,
        deadline_at: fromLocalInput(basics.deadline),
    };
}

/**
 * Autosaves the current step; true when saved (or nothing to save).
 */
async function saveCurrent(): Promise<boolean> {
    const key = current.value;

    if (key === 'review') {
        return true;
    }

    saveState.value = 'saving';
    errors.value = {};

    if (props.activity === null) {
        const result = await sendJson<{ id: string }>(
            'POST',
            store().url,
            basicsPayload(),
        );

        if (!result.ok) {
            errors.value = problemErrors(result.problem);
            saveState.value = 'failed';

            return false;
        }

        saveState.value = 'saved';
        // The draft exists now: continue in its own wizard.
        router.visit(edit(result.body.id, { query: { step: 'settings' } }));

        return false;
    }

    const result = await sendJson<{ steps: WizardStep[] }>(
        'PUT',
        saveStep({ activity: props.activity.id, step: key }).url,
        key === 'basics' ? basicsPayload() : stepData[key],
    );

    if (!result.ok) {
        errors.value = problemErrors(result.problem);

        if (result.problem?.detail && Object.keys(errors.value).length === 0) {
            errors.value = { _step: result.problem.detail };
        }

        saveState.value = 'failed';

        return false;
    }

    steps.value = result.body.steps;
    saveState.value = 'saved';

    return true;
}

async function goTo(key: WizardStepKey): Promise<void> {
    if (key === current.value) {
        return;
    }

    if (await saveCurrent()) {
        current.value = key;
    }
}

async function next(): Promise<void> {
    const following = steps.value[currentIndex.value + 1];

    if (following) {
        await goTo(following.key);
    } else {
        await saveCurrent();
    }
}

async function back(): Promise<void> {
    const previous = steps.value[currentIndex.value - 1];

    if (previous) {
        await goTo(previous.key);
    }
}

function publishActivity(): void {
    if (props.activity === null) {
        return;
    }

    publishing.value = true;
    errors.value = {};
    router.post(
        publish(props.activity.id).url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onError: (publishErrors) => {
                errors.value = publishErrors as StepErrors;
            },
            onFinish: () => {
                publishing.value = false;
            },
        },
    );
}

const reviewErrors = computed(() => Object.values(errors.value));
</script>

<template>
    <Head
        :title="
            isNew
                ? $t('activities.wizard.title_create')
                : $t('activities.wizard.title_edit')
        "
    />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-medium">
                {{
                    isNew
                        ? $t('activities.wizard.title_create')
                        : $t('activities.wizard.title_edit')
                }}
            </h1>
            <p v-if="activity" class="text-sm text-muted-foreground">
                {{ $t(`activities.types.${activity.type}`) }} ·
                {{ activity.course_space_title }} ·
                {{ $t(`activities.statuses.${activity.status}`) }}
            </p>
        </div>

        <Alert v-if="isNew && !canCreate" data-test="wizard-unavailable">
            <AlertDescription>
                {{
                    types.length === 0
                        ? $t('activities.wizard.no_types')
                        : $t('activities.wizard.no_course_spaces')
                }}
            </AlertDescription>
        </Alert>

        <template v-else>
            <ol class="flex flex-wrap gap-2" data-test="wizard-steps">
                <li v-for="(item, position) in steps" :key="item.key">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm disabled:opacity-50"
                        :class="
                            item.key === current
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input'
                        "
                        :disabled="isNew && item.key !== 'basics'"
                        :aria-current="
                            item.key === current ? 'step' : undefined
                        "
                        :data-test="`wizard-step-${item.key}`"
                        @click="goTo(item.key)"
                    >
                        <span>{{ position + 1 }}.</span>
                        <span>{{
                            $t(`activities.wizard.steps.${item.key}`)
                        }}</span>
                        <Check
                            v-if="item.complete"
                            class="size-4"
                            :aria-label="$t('activities.wizard.completed')"
                        />
                    </button>
                </li>
            </ol>

            <p class="text-sm text-muted-foreground">
                {{
                    $t('activities.wizard.step_of', {
                        current: String(currentIndex + 1),
                        total: String(steps.length),
                    })
                }}
            </p>

            <Alert v-if="gradingLocked" data-test="wizard-grading-locked">
                <AlertDescription>{{
                    $t('activities.wizard.grading_locked')
                }}</AlertDescription>
            </Alert>

            <!-- Step 1: basics -->
            <section v-if="current === 'basics'" class="flex flex-col gap-6">
                <div class="grid gap-2">
                    <Label for="course_space_id">{{
                        $t('activities.form.course_space')
                    }}</Label>
                    <select
                        id="course_space_id"
                        v-model="basics.course_space_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-50 dark:bg-input/30"
                        :disabled="!isNew"
                        data-test="wizard-course-space"
                    >
                        <option v-if="!isNew" :value="basics.course_space_id">
                            {{ activity?.course_space_title }}
                        </option>
                        <template v-else>
                            <option value="" disabled>
                                {{
                                    $t('activities.wizard.choose_course_space')
                                }}
                            </option>
                            <option
                                v-for="space in courseSpaces"
                                :key="space.id"
                                :value="space.id"
                            >
                                {{ space.title }}
                            </option>
                        </template>
                    </select>
                    <InputError :message="errors.course_space_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="type">{{ $t('activities.form.type') }}</Label>
                    <select
                        id="type"
                        v-model="basics.type"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-50 dark:bg-input/30"
                        :disabled="!isNew"
                        data-test="wizard-type"
                    >
                        <option v-if="!isNew" :value="basics.type">
                            {{ $t(`activities.types.${basics.type}`) }}
                        </option>
                        <template v-else>
                            <option value="" disabled>
                                {{ $t('activities.wizard.choose_type') }}
                            </option>
                            <option
                                v-for="kind in types"
                                :key="kind"
                                :value="kind"
                            >
                                {{ $t(`activities.types.${kind}`) }}
                            </option>
                        </template>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('activities.wizard.type_fixed') }}
                    </p>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="title">{{ $t('activities.form.title') }}</Label>
                    <Input
                        id="title"
                        v-model="basics.title"
                        required
                        maxlength="255"
                        data-test="wizard-title"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">{{
                        $t('activities.form.description')
                    }}</Label>
                    <textarea
                        id="description"
                        v-model="basics.description"
                        rows="4"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        data-test="wizard-description"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="deadline">{{
                        $t('activities.form.deadline')
                    }}</Label>
                    <Input
                        id="deadline"
                        v-model="basics.deadline"
                        type="datetime-local"
                        data-test="wizard-deadline"
                    />
                    <InputError :message="errors.deadline_at" />
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ $t('activities.wizard.individual') }}
                </p>
            </section>

            <!-- Steps owned by the activity type -->
            <section
                v-else-if="current === 'settings' || current === 'content'"
                class="flex flex-col gap-6"
                :data-test="`wizard-${current}`"
            >
                <component
                    :is="stepComponents[current]"
                    v-if="stepComponents[current]"
                    v-model="stepData[current]"
                    :errors="errors"
                    :activity="activity"
                    :locked="gradingLocked"
                />
                <Alert v-else>
                    <AlertDescription>{{
                        $t('activities.wizard.type_unavailable')
                    }}</AlertDescription>
                </Alert>
                <InputError :message="errors._step" />
            </section>

            <!-- Review -->
            <section
                v-else-if="current === 'review' && activity"
                class="flex flex-col gap-4"
                data-test="wizard-review"
            >
                <p class="text-sm">
                    {{
                        activity.status === 'draft'
                            ? $t('activities.wizard.review_hint')
                            : $t('activities.wizard.published_notice')
                    }}
                </p>
                <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
                    <dt class="text-muted-foreground">
                        {{ $t('activities.form.title') }}
                    </dt>
                    <dd>{{ basics.title }}</dd>
                    <dt class="text-muted-foreground">
                        {{ $t('activities.form.course_space') }}
                    </dt>
                    <dd>{{ activity.course_space_title }}</dd>
                    <template v-for="item in steps" :key="item.key">
                        <template v-if="item.key !== 'review'">
                            <dt class="text-muted-foreground">
                                {{ $t(`activities.wizard.steps.${item.key}`) }}
                            </dt>
                            <dd>
                                <Check
                                    v-if="item.complete"
                                    class="size-4"
                                    :aria-label="
                                        $t('activities.wizard.completed')
                                    "
                                />
                            </dd>
                        </template>
                    </template>
                </dl>
                <p
                    v-if="activity.status === 'draft' && !readyForReview(steps)"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('activities.wizard.review_incomplete') }}
                </p>
                <ul
                    v-if="reviewErrors.length > 0"
                    class="list-disc pl-5 text-sm text-red-600 dark:text-red-500"
                    data-test="wizard-publish-errors"
                >
                    <li v-for="message in reviewErrors" :key="message">
                        {{ message }}
                    </li>
                </ul>
            </section>

            <p
                v-if="saveState === 'failed'"
                class="text-sm text-red-600 dark:text-red-500"
                role="alert"
            >
                {{ $t('activities.wizard.save_failed') }}
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    v-if="currentIndex > 0"
                    variant="outline"
                    type="button"
                    data-test="wizard-back"
                    @click="back"
                >
                    {{ $t('activities.wizard.back') }}
                </Button>
                <Button
                    v-if="current !== 'review'"
                    type="button"
                    :disabled="saveState === 'saving'"
                    data-test="wizard-next"
                    @click="next"
                >
                    <Spinner v-if="saveState === 'saving'" />
                    {{ $t('activities.wizard.next') }}
                </Button>
                <template v-else-if="activity">
                    <Button variant="outline" as-child>
                        <Link
                            :href="show(activity.id)"
                            data-test="wizard-save-draft"
                        >
                            {{
                                activity.status === 'draft'
                                    ? $t('activities.wizard.save_draft')
                                    : $t('activities.wizard.done')
                            }}
                        </Link>
                    </Button>
                    <Button
                        v-if="can.publish"
                        type="button"
                        :disabled="publishing"
                        data-test="wizard-publish"
                        @click="publishActivity"
                    >
                        <Spinner v-if="publishing" />
                        {{ $t('activities.wizard.publish') }}
                    </Button>
                </template>
                <span
                    v-if="saveState === 'saving' || saveState === 'saved'"
                    class="text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {{
                        saveState === 'saving'
                            ? $t('activities.wizard.saving')
                            : $t('activities.wizard.saved')
                    }}
                </span>
            </div>
        </template>
    </div>
</template>
