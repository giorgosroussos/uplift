<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { canAct, defaultGroup } from '@/lib/topicForm';
import { edit, index, show as showActivity } from '@/routes/activities';
import {
    cancel as cancelRoute,
    choose as chooseRoute,
} from '@/routes/activities/topics';
import type { TopicGroup, TopicSelectionDetails } from '@/types/topics';

/*
 * UC-014: a topic selection as a student (choose, switch or cancel a
 * topic until the deadline, alone or for one of their groups) or its
 * teacher (who holds which topic, OWN-008) sees it. Everybody sees the
 * remaining places of every topic.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    topicSelection: TopicSelectionDetails;
    choice: { topic_id: string } | null;
    groups: TopicGroup[];
    can: { choose: boolean; viewHolders: boolean; update: boolean };
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);
const actingGroup = ref<string | null>(defaultGroup(props.groups));
const submitting = ref(false);

const selectedGroup = computed(
    () => props.groups.find((group) => group.id === actingGroup.value) ?? null,
);
const currentTopicId = computed(() =>
    props.topicSelection.is_group
        ? (selectedGroup.value?.topic_id ?? null)
        : (props.choice?.topic_id ?? null),
);
const mayChoose = computed(
    () =>
        props.can.choose &&
        (!props.topicSelection.is_group ||
            (selectedGroup.value !== null && canAct(selectedGroup.value))),
);

function topicTitle(id: string | null): string | null {
    return (
        props.topicSelection.topics.find((topic) => topic.id === id)?.title ??
        null
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function groupPayload(): Record<string, string> {
    return props.topicSelection.is_group && actingGroup.value
        ? { group_id: actingGroup.value }
        : {};
}

function choose(topicId: string): void {
    submitting.value = true;
    router.put(
        chooseRoute(props.topicSelection.activity_id).url,
        { topic_id: topicId, ...groupPayload() },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function cancelChoice(): void {
    submitting.value = true;
    router.delete(cancelRoute(props.topicSelection.activity_id).url, {
        data: groupPayload(),
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <Head :title="topicSelection.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="topics-title">
                    {{ topicSelection.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{{
                        $t('activities.types.topic_selection')
                    }}</Badge>
                    <Badge variant="outline" data-test="topics-status">
                        {{ $t(`activities.statuses.${topicSelection.status}`) }}
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="showActivity(topicSelection.activity_id)">
                        {{ $t('activities.wizard.back') }}
                    </Link>
                </Button>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(topicSelection.activity_id)">
                        {{ $t('activities.show.edit') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="topicSelection.description"
            class="text-sm whitespace-pre-line"
        >
            {{ topicSelection.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('activities.show.course_space') }}
            </dt>
            <dd>{{ topicSelection.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('activities.show.deadline') }}
            </dt>
            <dd>
                {{
                    topicSelection.deadline_at
                        ? formatDate(topicSelection.deadline_at)
                        : $t('activities.show.no_deadline')
                }}
            </dd>
        </dl>

        <p
            v-if="topicSelection.is_group"
            class="text-sm text-muted-foreground"
            data-test="topics-group-activity"
        >
            {{
                $t('activities.topics.show.group_activity', {
                    size: String(topicSelection.group_size ?? ''),
                })
            }}
        </p>

        <Alert
            v-if="!topicSelection.is_open && topicSelection.status !== 'draft'"
            data-test="topics-closed"
        >
            <AlertDescription>{{
                $t('activities.topics.show.closed')
            }}</AlertDescription>
        </Alert>

        <!-- The student's own choice, or their groups' choices (OWN-008). -->
        <section
            v-if="
                !can.viewHolders &&
                !topicSelection.is_group &&
                (can.choose || choice)
            "
            class="flex flex-col gap-2 rounded-md border p-4"
            data-test="topics-my-choice"
        >
            <h2 class="text-sm font-medium">
                {{ $t('activities.topics.show.your_choice') }}
            </h2>
            <p class="text-sm" data-test="topics-current">
                {{
                    topicTitle(choice?.topic_id ?? null) ??
                    $t('activities.topics.show.no_choice')
                }}
            </p>
        </section>

        <section
            v-if="
                !can.viewHolders &&
                topicSelection.is_group &&
                topicSelection.status !== 'draft'
            "
            class="flex flex-col gap-3 rounded-md border p-4"
            data-test="topics-groups"
        >
            <Alert v-if="groups.length === 0" data-test="topics-no-groups">
                <AlertDescription>{{
                    $t('activities.topics.show.no_groups')
                }}</AlertDescription>
            </Alert>
            <template v-else>
                <ul class="flex flex-col gap-1 text-sm">
                    <li
                        v-for="(group, position) in groups"
                        :key="group.id"
                        :data-test="`topics-group-choice-${position}`"
                    >
                        <span v-if="group.topic_id">
                            {{
                                $t('activities.topics.show.group_choice', {
                                    group: group.name,
                                })
                            }}:
                            <strong>{{ topicTitle(group.topic_id) }}</strong>
                        </span>
                        <span v-else class="text-muted-foreground">
                            {{
                                $t('activities.topics.show.group_no_choice', {
                                    group: group.name,
                                })
                            }}
                        </span>
                    </li>
                </ul>
                <div v-if="can.choose" class="grid gap-2">
                    <Label for="acting-group">{{
                        $t('activities.topics.show.acting_group')
                    }}</Label>
                    <select
                        id="acting-group"
                        v-model="actingGroup"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                        data-test="topics-acting-group"
                    >
                        <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{
                                $t('activities.topics.show.group_option', {
                                    name: group.name,
                                    members: String(group.members),
                                })
                            }}
                        </option>
                    </select>
                    <p
                        v-if="selectedGroup?.too_large"
                        class="text-sm text-muted-foreground"
                        data-test="topics-group-too-large"
                    >
                        {{ $t('activities.topics.show.group_too_large') }}
                    </p>
                    <p
                        v-else-if="selectedGroup?.in_other_group"
                        class="text-sm text-muted-foreground"
                        data-test="topics-group-in-other"
                    >
                        {{ $t('activities.topics.show.group_in_other') }}
                    </p>
                </div>
            </template>
        </section>

        <p v-if="can.choose" class="text-xs text-muted-foreground">
            {{ $t('activities.topics.show.deadline_hint') }}
        </p>
        <InputError :message="errors.topic" />
        <InputError :message="errors.group_id" />

        <ul class="flex flex-col gap-3" data-test="topics-list">
            <li
                v-for="(topic, position) in topicSelection.topics"
                :key="topic.id"
                class="flex flex-col gap-2 rounded-md border p-4"
                :data-test="`topic-row-${position}`"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ topic.title }}</span>
                        <span
                            class="text-sm text-muted-foreground"
                            :data-test="`topic-remaining-${position}`"
                        >
                            {{
                                $tChoice(
                                    'activities.topics.show.remaining',
                                    topic.remaining,
                                    { capacity: String(topic.capacity) },
                                )
                            }}
                        </span>
                    </div>
                    <Badge
                        v-if="currentTopicId === topic.id"
                        :data-test="`topic-chosen-${position}`"
                    >
                        {{ $t('activities.topics.show.chosen') }}
                    </Badge>
                    <Button
                        v-else-if="mayChoose && topic.remaining > 0"
                        size="sm"
                        :variant="currentTopicId ? 'outline' : 'default'"
                        :disabled="submitting"
                        :data-test="`topic-choose-${position}`"
                        @click="choose(topic.id)"
                    >
                        {{
                            currentTopicId
                                ? $t('activities.topics.show.switch')
                                : $t('activities.topics.show.choose')
                        }}
                    </Button>
                </div>
                <p v-if="topic.description" class="text-sm whitespace-pre-line">
                    {{ topic.description }}
                </p>
                <p
                    v-if="can.viewHolders"
                    class="text-sm"
                    :data-test="`topic-holders-${position}`"
                >
                    <span class="text-muted-foreground"
                        >{{ $t('activities.topics.show.holders') }}:</span
                    >
                    {{
                        topic.holders && topic.holders.length > 0
                            ? topic.holders.join(', ')
                            : $t('activities.topics.show.no_holders')
                    }}
                </p>
            </li>
        </ul>

        <div v-if="can.choose && currentTopicId">
            <Button
                variant="destructive"
                :disabled="submitting"
                data-test="topics-cancel"
                @click="cancelChoice"
            >
                {{
                    topicSelection.is_group
                        ? $t('activities.topics.show.cancel_group')
                        : $t('activities.topics.show.cancel')
                }}
            </Button>
        </div>
    </div>
</template>
