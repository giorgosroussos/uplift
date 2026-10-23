<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show as showSpace } from '@/routes/courses';
import { show, store } from '@/routes/courses/groups';
import { accept, decline } from '@/routes/courses/groups/invitations';
import type { GroupDetails, GroupSpace } from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'courses.nav.courses', href: index() }],
    },
});

const props = defineProps<{
    space: GroupSpace;
    groups: GroupDetails[];
    canCreate: boolean;
}>();

const createForm = useForm({ name: '' });

function submitGroup(): void {
    createForm.submit(store(props.space.id), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function respond(group: GroupDetails, answer: 'accept' | 'decline'): void {
    if (group.own === null) {
        return;
    }

    const args = {
        courseSpace: props.space.id,
        group: group.id,
        membership: group.own.membership_id,
    };

    router.visit(answer === 'accept' ? accept(args) : decline(args), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="$t('groups.index.title')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-medium" data-test="groups-heading">
                {{ $t('groups.index.heading', { space: space.title }) }}
            </h1>
            <Link
                :href="showSpace(space.id)"
                class="text-sm text-muted-foreground underline"
            >
                {{ $t('groups.index.back') }}
            </Link>
        </div>

        <p
            v-if="!canCreate"
            class="text-sm text-muted-foreground"
            data-test="groups-read-only"
        >
            {{ $t('groups.index.read_only') }}
        </p>

        <form
            v-if="canCreate"
            class="flex flex-wrap items-end gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            data-test="group-create-form"
            @submit.prevent="submitGroup"
        >
            <div class="grid min-w-64 gap-2">
                <Label for="group_name">{{ $t('groups.index.name') }}</Label>
                <Input
                    id="group_name"
                    v-model="createForm.name"
                    name="name"
                    required
                    maxlength="255"
                    data-test="group-name"
                />
                <InputError :message="createForm.errors.name" />
            </div>
            <Button
                type="submit"
                :disabled="createForm.processing"
                data-test="group-create"
            >
                {{ $t('groups.index.submit') }}
            </Button>
        </form>

        <p
            v-if="groups.length === 0"
            class="text-sm text-muted-foreground"
            data-test="groups-empty"
        >
            {{
                canCreate
                    ? $t('groups.index.empty_student')
                    : $t('groups.index.empty')
            }}
        </p>

        <ul v-else class="flex flex-col gap-4">
            <li
                v-for="group in groups"
                :key="group.id"
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                data-test="group"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-medium" data-test="group-name-label">
                        {{ group.name }}
                    </h2>
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="
                                show({ courseSpace: space.id, group: group.id })
                            "
                            data-test="group-open"
                        >
                            {{ $t('groups.index.open') }}
                        </Link>
                    </Button>
                </div>

                <p class="text-sm">
                    {{ $t('groups.show.owner') }}: {{ group.owner.name }}
                </p>
                <p class="text-sm" data-test="group-members">
                    {{ $t('groups.show.members') }}:
                    {{ group.members.map((member) => member.name).join(', ') }}
                </p>
                <p
                    v-if="group.invitations.length > 0"
                    class="text-sm text-muted-foreground"
                    data-test="group-invitations"
                >
                    {{ $t('groups.show.invitations') }}:
                    {{
                        group.invitations
                            .map((invitation) => invitation.name)
                            .join(', ')
                    }}
                </p>

                <div
                    v-if="group.can.respond"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="text-sm font-medium">
                        {{ $t('groups.index.invited_you') }}
                    </span>
                    <Button
                        size="sm"
                        data-test="invitation-accept"
                        @click="respond(group, 'accept')"
                    >
                        {{ $t('groups.show.accept') }}
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        data-test="invitation-decline"
                        @click="respond(group, 'decline')"
                    >
                        {{ $t('groups.show.decline') }}
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
