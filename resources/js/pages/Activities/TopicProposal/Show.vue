<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    canActFor,
    defaultProposalGroup,
    latestStatus,
    proposalPayload,
} from '@/lib/proposalForm';
import { edit, index, show as showActivity } from '@/routes/activities';
import {
    decide as decideRoute,
    propose as proposeRoute,
} from '@/routes/activities/proposals';
import {
    MAX_PROPOSAL_DESCRIPTION,
    MAX_PROPOSAL_TITLE,
} from '@/types/proposals';
import type {
    OwnProposals,
    ProposalGroup,
    ProposalRow,
    ReviewedProposal,
    TopicProposalDetails,
    TopicProposalStatus,
} from '@/types/proposals';

/*
 * UC-015 and UC-027: a topic proposal activity as a student (propose a
 * topic until the deadline, alone or for one of their groups, and follow
 * its status) or its teacher (approve, or reject with a comment, every
 * proposal; OWN-009) sees it.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    topicProposal: TopicProposalDetails;
    own: OwnProposals | null;
    groups: ProposalGroup[];
    proposals: ReviewedProposal[];
    can: { propose: boolean; decide: boolean; update: boolean };
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const actingGroup = ref<string | null>(defaultProposalGroup(props.groups));
const title = ref('');
const description = ref('');
const submitting = ref(false);
const comments = reactive<Record<string, string>>({});
const deciding = ref<string | null>(null);

const selectedGroup = computed(
    () => props.groups.find((group) => group.id === actingGroup.value) ?? null,
);
const holderProposals = computed<ProposalRow[]>(() =>
    props.topicProposal.is_group
        ? (selectedGroup.value?.proposals ?? [])
        : (props.own?.proposals ?? []),
);
const holderStatus = computed<TopicProposalStatus | null>(() =>
    latestStatus(holderProposals.value),
);
const mayPropose = computed(() => {
    if (!props.can.propose) {
        return false;
    }

    if (!props.topicProposal.is_group) {
        return props.own?.may_propose ?? false;
    }

    return (
        selectedGroup.value !== null &&
        selectedGroup.value.may_propose &&
        canActFor(selectedGroup.value)
    );
});
const showsOwn = computed(
    () =>
        !props.can.decide &&
        props.topicProposal.status !== 'draft' &&
        (props.can.propose || holderProposals.value.length > 0),
);

function statusVariant(
    status: TopicProposalStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'approved') {
        return 'default';
    }

    return status === 'rejected' ? 'destructive' : 'secondary';
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function propose(): void {
    submitting.value = true;
    router.post(
        proposeRoute(props.topicProposal.activity_id).url,
        proposalPayload(
            title.value,
            description.value,
            props.topicProposal.is_group ? actingGroup.value : null,
        ),
        {
            preserveScroll: true,
            onSuccess: () => {
                title.value = '';
                description.value = '';
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function decide(
    proposal: ReviewedProposal,
    decision: 'approve' | 'reject',
): void {
    deciding.value = proposal.id;
    submitting.value = true;
    router.put(
        decideRoute({
            activity: props.topicProposal.activity_id,
            proposal: proposal.id,
        }).url,
        { decision, comment: comments[proposal.id] ?? '' },
        {
            preserveScroll: true,
            onSuccess: () => {
                delete comments[proposal.id];
                deciding.value = null;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="topicProposal.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="proposals-title">
                    {{ topicProposal.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{{
                        $t('activities.types.topic_proposal')
                    }}</Badge>
                    <Badge variant="outline" data-test="proposals-status">
                        {{ $t(`activities.statuses.${topicProposal.status}`) }}
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="showActivity(topicProposal.activity_id)">
                        {{ $t('activities.wizard.back') }}
                    </Link>
                </Button>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(topicProposal.activity_id)">
                        {{ $t('activities.show.edit') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p v-if="topicProposal.description" class="text-sm whitespace-pre-line">
            {{ topicProposal.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('activities.show.course_space') }}
            </dt>
            <dd>{{ topicProposal.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('activities.show.deadline') }}
            </dt>
            <dd>
                {{
                    topicProposal.deadline_at
                        ? formatDate(topicProposal.deadline_at)
                        : $t('activities.show.no_deadline')
                }}
            </dd>
        </dl>

        <p
            v-if="topicProposal.is_group"
            class="text-sm text-muted-foreground"
            data-test="proposals-group-activity"
        >
            {{
                $t('activities.proposals.show.group_activity', {
                    size: String(topicProposal.group_size ?? ''),
                })
            }}
        </p>

        <Alert
            v-if="!topicProposal.is_open && topicProposal.status !== 'draft'"
            data-test="proposals-closed"
        >
            <AlertDescription>{{
                $t('activities.proposals.show.closed')
            }}</AlertDescription>
        </Alert>

        <!-- The student's own proposals, or their groups' (OWN-009). -->
        <template v-if="showsOwn">
            <section
                v-if="topicProposal.is_group"
                class="flex flex-col gap-3 rounded-md border p-4"
                data-test="proposals-groups"
            >
                <Alert
                    v-if="groups.length === 0"
                    data-test="proposals-no-groups"
                >
                    <AlertDescription>{{
                        $t('activities.proposals.show.no_groups')
                    }}</AlertDescription>
                </Alert>
                <div v-else class="grid gap-2">
                    <Label for="acting-group">{{
                        $t('activities.proposals.show.acting_group')
                    }}</Label>
                    <select
                        id="acting-group"
                        v-model="actingGroup"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                        data-test="proposals-acting-group"
                    >
                        <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{
                                $t('activities.proposals.show.group_option', {
                                    name: group.name,
                                    members: String(group.members),
                                })
                            }}
                        </option>
                    </select>
                    <p
                        v-if="selectedGroup?.too_large"
                        class="text-sm text-muted-foreground"
                        data-test="proposals-group-too-large"
                    >
                        {{ $t('activities.proposals.show.group_too_large') }}
                    </p>
                    <p
                        v-else-if="selectedGroup?.in_other_group"
                        class="text-sm text-muted-foreground"
                        data-test="proposals-group-in-other"
                    >
                        {{ $t('activities.proposals.show.group_in_other') }}
                    </p>
                </div>
            </section>

            <section
                v-if="!topicProposal.is_group || selectedGroup"
                class="flex flex-col gap-3"
                data-test="proposals-mine"
            >
                <h2 class="text-sm font-medium">
                    {{
                        topicProposal.is_group
                            ? $t('activities.proposals.show.group_proposals', {
                                  group: selectedGroup?.name ?? '',
                              })
                            : $t('activities.proposals.show.your_proposals')
                    }}
                </h2>

                <p
                    v-if="holderProposals.length === 0"
                    class="text-sm text-muted-foreground"
                    data-test="proposals-none"
                >
                    {{
                        topicProposal.is_group
                            ? $t(
                                  'activities.proposals.show.group_no_proposals',
                                  {
                                      group: selectedGroup?.name ?? '',
                                  },
                              )
                            : $t('activities.proposals.show.no_proposals')
                    }}
                </p>
                <Alert
                    v-else-if="holderStatus"
                    :data-test="`proposals-notice-${holderStatus}`"
                >
                    <AlertDescription>{{
                        $t(
                            holderStatus === 'pending'
                                ? 'activities.proposals.show.pending_notice'
                                : holderStatus === 'approved'
                                  ? 'activities.proposals.show.approved_notice'
                                  : 'activities.proposals.show.rejected_hint',
                        )
                    }}</AlertDescription>
                </Alert>

                <ul class="flex flex-col gap-3">
                    <li
                        v-for="(proposal, position) in holderProposals"
                        :key="proposal.id"
                        class="flex flex-col gap-2 rounded-md border p-4"
                        :data-test="`proposal-own-${position}`"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <span class="font-medium">{{
                                proposal.title
                            }}</span>
                            <Badge
                                :variant="statusVariant(proposal.status)"
                                :data-test="`proposal-own-status-${position}`"
                            >
                                {{
                                    $t(
                                        `activities.proposals.statuses.${proposal.status}`,
                                    )
                                }}
                            </Badge>
                        </div>
                        <p
                            v-if="proposal.description"
                            class="text-sm whitespace-pre-line"
                        >
                            {{ proposal.description }}
                        </p>
                        <p
                            v-if="proposal.teacher_comment"
                            class="text-sm"
                            :data-test="`proposal-own-comment-${position}`"
                        >
                            <span class="text-muted-foreground"
                                >{{
                                    $t(
                                        'activities.proposals.show.teacher_comment',
                                    )
                                }}:</span
                            >
                            {{ proposal.teacher_comment }}
                        </p>
                    </li>
                </ul>
            </section>

            <form
                v-if="mayPropose"
                class="flex flex-col gap-3 rounded-md border p-4"
                data-test="proposals-form"
                @submit.prevent="propose"
            >
                <h2 class="text-sm font-medium">
                    {{ $t('activities.proposals.show.form_title') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ $t('activities.proposals.show.deadline_hint') }}
                </p>
                <div class="grid gap-2">
                    <Label for="proposal-title">{{
                        $t('activities.proposals.show.title')
                    }}</Label>
                    <Input
                        id="proposal-title"
                        v-model="title"
                        :maxlength="MAX_PROPOSAL_TITLE"
                        required
                        data-test="proposal-title"
                    />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="proposal-description">{{
                        $t('activities.proposals.show.description')
                    }}</Label>
                    <textarea
                        id="proposal-description"
                        v-model="description"
                        rows="4"
                        :maxlength="MAX_PROPOSAL_DESCRIPTION"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        data-test="proposal-description"
                    />
                    <InputError :message="errors.description" />
                </div>
                <div>
                    <Button
                        type="submit"
                        :disabled="submitting || title.trim() === ''"
                        data-test="proposal-submit"
                    >
                        {{ $t('activities.proposals.show.submit') }}
                    </Button>
                </div>
            </form>
            <InputError :message="errors.proposal" />
            <InputError :message="errors.group_id" />
        </template>

        <!-- Every proposal, for the owning teacher to decide (UC-027). -->
        <section
            v-if="can.decide"
            class="flex flex-col gap-3"
            data-test="proposals-review"
        >
            <h2 class="text-sm font-medium">
                {{ $t('activities.proposals.show.all_title') }}
            </h2>
            <p
                v-if="proposals.length === 0"
                class="text-sm text-muted-foreground"
                data-test="proposals-review-empty"
            >
                {{ $t('activities.proposals.show.none_yet') }}
            </p>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="(proposal, position) in proposals"
                    :key="proposal.id"
                    class="flex flex-col gap-2 rounded-md border p-4"
                    :data-test="`proposal-review-${position}`"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="flex flex-col gap-1">
                            <span class="font-medium">{{
                                proposal.title
                            }}</span>
                            <span class="text-sm text-muted-foreground">
                                {{ $t('activities.proposals.show.proposer') }}:
                                {{ proposal.proposer }}
                                <template v-if="proposal.created_at">
                                    · {{ formatDate(proposal.created_at) }}
                                </template>
                            </span>
                        </div>
                        <Badge
                            :variant="statusVariant(proposal.status)"
                            :data-test="`proposal-review-status-${position}`"
                        >
                            {{
                                $t(
                                    `activities.proposals.statuses.${proposal.status}`,
                                )
                            }}
                        </Badge>
                    </div>
                    <p
                        v-if="proposal.description"
                        class="text-sm whitespace-pre-line"
                    >
                        {{ proposal.description }}
                    </p>
                    <p
                        v-if="proposal.teacher_comment"
                        class="text-sm"
                        :data-test="`proposal-review-comment-${position}`"
                    >
                        <span class="text-muted-foreground"
                            >{{
                                $t('activities.proposals.show.teacher_comment')
                            }}:</span
                        >
                        {{ proposal.teacher_comment }}
                    </p>
                    <div
                        v-if="proposal.status === 'pending'"
                        class="flex flex-col gap-2"
                    >
                        <Label :for="`proposal-comment-${position}`">{{
                            $t('activities.proposals.show.comment')
                        }}</Label>
                        <textarea
                            :id="`proposal-comment-${position}`"
                            v-model="comments[proposal.id]"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                            :data-test="`proposal-comment-${position}`"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('activities.proposals.show.comment_hint') }}
                        </p>
                        <template v-if="deciding === proposal.id">
                            <InputError :message="errors.comment" />
                            <InputError :message="errors.proposal" />
                            <InputError :message="errors.decision" />
                        </template>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                :disabled="submitting"
                                :data-test="`proposal-approve-${position}`"
                                @click="decide(proposal, 'approve')"
                            >
                                {{ $t('activities.proposals.show.approve') }}
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                :disabled="submitting"
                                :data-test="`proposal-reject-${position}`"
                                @click="decide(proposal, 'reject')"
                            >
                                {{ $t('activities.proposals.show.reject') }}
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
