<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import { index } from '@/routes/courses';
import {
    destroy,
    index as groupsIndex,
    leave,
    owner,
    update,
} from '@/routes/courses/groups';
import {
    accept,
    decline,
    destroy as withdraw,
    store as invite,
} from '@/routes/courses/groups/invitations';
import type {
    GroupDetails,
    GroupSpace,
    InvitableStudent,
} from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'courses.nav.courses', href: index() }],
    },
});

const props = defineProps<{
    space: GroupSpace;
    group: GroupDetails;
    invitableStudents: InvitableStudent[];
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const groupArgs = computed(() => ({
    courseSpace: props.space.id,
    group: props.group.id,
}));

const otherMembers = computed(() =>
    props.group.members.filter(
        (member) => member.student_id !== props.group.owner.id,
    ),
);

const renameForm = useForm({ name: props.group.name });
const inviteForm = useForm({ student_id: '' });
const transferForm = useForm({ student_id: '' });

const inviting = ref(false);
const transferring = ref(false);
const confirmingLeave = ref(false);
const confirmingDissolve = ref(false);

function submitRename(): void {
    renameForm.submit(update(groupArgs.value), { preserveScroll: true });
}

function submitInvite(): void {
    inviteForm.submit(invite(groupArgs.value), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviting.value = false;
        },
    });
}

function submitTransfer(): void {
    transferForm.submit(owner(groupArgs.value), {
        preserveScroll: true,
        onSuccess: () => {
            transferForm.reset();
            transferring.value = false;
        },
    });
}

function withdrawInvitation(membershipId: string): void {
    router.visit(withdraw({ ...groupArgs.value, membership: membershipId }), {
        preserveScroll: true,
    });
}

function respond(answer: 'accept' | 'decline'): void {
    if (props.group.own === null) {
        return;
    }

    const args = {
        ...groupArgs.value,
        membership: props.group.own.membership_id,
    };

    router.visit(answer === 'accept' ? accept(args) : decline(args), {
        preserveScroll: true,
    });
}

function leaveGroup(): void {
    router.visit(leave(groupArgs.value), {
        onFinish: () => {
            confirmingLeave.value = false;
        },
    });
}

function dissolveGroup(): void {
    router.visit(destroy(groupArgs.value), {
        onFinish: () => {
            confirmingDissolve.value = false;
        },
    });
}

function formatDate(value: string | null): string {
    return value === null
        ? ''
        : new Date(value).toLocaleDateString(
              document.documentElement.lang || undefined,
          );
}
</script>

<template>
    <Head :title="group.name" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-medium" data-test="group-title">
                {{ group.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ space.title }} · {{ $t('groups.show.owner') }}:
                {{ group.owner.name }}
            </p>
            <Link
                :href="groupsIndex(space.id)"
                class="text-sm text-muted-foreground underline"
            >
                {{ $t('groups.show.back') }}
            </Link>
        </div>

        <InputError :message="errors.group" />
        <InputError :message="errors.invitation" />

        <div v-if="group.can.respond" class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium">
                {{ $t('groups.index.invited_you') }}
            </span>
            <Button
                size="sm"
                data-test="invitation-accept"
                @click="respond('accept')"
            >
                {{ $t('groups.show.accept') }}
            </Button>
            <Button
                size="sm"
                variant="outline"
                data-test="invitation-decline"
                @click="respond('decline')"
            >
                {{ $t('groups.show.decline') }}
            </Button>
        </div>

        <form
            v-if="group.can.update"
            class="flex flex-wrap items-end gap-4"
            data-test="group-rename-form"
            @submit.prevent="submitRename"
        >
            <div class="grid min-w-64 gap-2">
                <Label for="group_name">{{ $t('groups.index.name') }}</Label>
                <Input
                    id="group_name"
                    v-model="renameForm.name"
                    name="name"
                    required
                    maxlength="255"
                    data-test="group-rename-name"
                />
                <InputError :message="renameForm.errors.name" />
            </div>
            <Button
                type="submit"
                variant="outline"
                :disabled="renameForm.processing"
                data-test="group-rename"
            >
                {{ $t('groups.show.rename') }}
            </Button>
        </form>

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">
                {{ $t('groups.show.members') }} ({{ group.members.length }})
            </h2>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="member in group.members"
                    :key="member.membership_id"
                    class="flex flex-wrap items-center gap-2"
                    data-test="group-member"
                >
                    <span>{{ member.name }}</span>
                    <Badge
                        v-if="member.student_id === group.owner.id"
                        variant="secondary"
                    >
                        {{ $t('groups.show.owner') }}
                    </Badge>
                    <span class="text-muted-foreground">
                        {{ $t('groups.show.joined_at') }}
                        {{ formatDate(member.joined_at) }}
                    </span>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">
                {{ $t('groups.show.invitations') }}
            </h2>
            <p
                v-if="group.invitations.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('groups.show.no_invitations') }}
            </p>
            <ul v-else class="flex flex-col gap-1 text-sm">
                <li
                    v-for="invitation in group.invitations"
                    :key="invitation.membership_id"
                    class="flex flex-wrap items-center gap-2"
                    data-test="group-invitation"
                >
                    <span>{{ invitation.name }}</span>
                    <Button
                        v-if="group.can.invite"
                        variant="ghost"
                        size="sm"
                        data-test="invitation-withdraw"
                        @click="withdrawInvitation(invitation.membership_id)"
                    >
                        {{ $t('groups.show.withdraw') }}
                    </Button>
                </li>
            </ul>
        </section>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="group.can.invite"
                data-test="group-invite-open"
                @click="inviting = true"
            >
                {{ $t('groups.show.invite') }}
            </Button>
            <Button
                v-if="group.can.transferOwnership && otherMembers.length > 0"
                variant="outline"
                data-test="group-transfer-open"
                @click="transferring = true"
            >
                {{ $t('groups.show.transfer') }}
            </Button>
            <Button
                v-if="group.can.leave"
                variant="outline"
                data-test="group-leave-open"
                @click="confirmingLeave = true"
            >
                {{ $t('groups.show.leave') }}
            </Button>
            <Button
                v-if="group.can.delete"
                variant="destructive"
                data-test="group-dissolve-open"
                @click="confirmingDissolve = true"
            >
                {{ $t('groups.show.dissolve') }}
            </Button>
        </div>

        <p
            v-if="group.can.transferOwnership && otherMembers.length > 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('groups.show.owner_leave_hint') }}
        </p>

        <Dialog v-model:open="inviting">
            <DialogContent data-test="invite-dialog">
                <DialogTitle>{{ $t('groups.show.invite_title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('groups.show.invite_text') }}
                </DialogDescription>
                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submitInvite"
                >
                    <select
                        v-model="inviteForm.student_id"
                        required
                        :disabled="invitableStudents.length === 0"
                        :aria-label="$t('groups.show.choose_student')"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                        data-test="invite-select"
                    >
                        <option value="" disabled>
                            {{
                                invitableStudents.length === 0
                                    ? $t('groups.show.no_invitable')
                                    : $t('groups.show.choose_student')
                            }}
                        </option>
                        <option
                            v-for="student in invitableStudents"
                            :key="student.id"
                            :value="student.id"
                        >
                            {{ student.name }}
                        </option>
                    </select>
                    <InputError :message="inviteForm.errors.student_id" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                {{ $t('groups.show.cancel') }}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="
                                inviteForm.processing ||
                                inviteForm.student_id === ''
                            "
                            data-test="invite-submit"
                        >
                            {{ $t('groups.show.invite') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="transferring">
            <DialogContent data-test="transfer-dialog">
                <DialogTitle>{{
                    $t('groups.show.transfer_title')
                }}</DialogTitle>
                <DialogDescription>
                    {{ $t('groups.show.transfer_text') }}
                </DialogDescription>
                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submitTransfer"
                >
                    <select
                        v-model="transferForm.student_id"
                        required
                        :aria-label="$t('groups.show.choose_member')"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                        data-test="transfer-select"
                    >
                        <option value="" disabled>
                            {{ $t('groups.show.choose_member') }}
                        </option>
                        <option
                            v-for="member in otherMembers"
                            :key="member.student_id"
                            :value="member.student_id"
                        >
                            {{ member.name }}
                        </option>
                    </select>
                    <InputError :message="transferForm.errors.student_id" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                {{ $t('groups.show.cancel') }}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="
                                transferForm.processing ||
                                transferForm.student_id === ''
                            "
                            data-test="transfer-submit"
                        >
                            {{ $t('groups.show.transfer') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="confirmingLeave">
            <DialogContent data-test="leave-dialog">
                <DialogTitle>
                    {{ $t('groups.show.confirm_leave_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ $t('groups.show.confirm_leave_text') }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('groups.show.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button data-test="leave-confirm" @click="leaveGroup">
                        {{ $t('groups.show.leave') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="confirmingDissolve">
            <DialogContent data-test="dissolve-dialog">
                <DialogTitle>
                    {{ $t('groups.show.confirm_dissolve_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ $t('groups.show.confirm_dissolve_text') }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('groups.show.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        data-test="dissolve-confirm"
                        @click="dissolveGroup"
                    >
                        {{ $t('groups.show.dissolve') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
