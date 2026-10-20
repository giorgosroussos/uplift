<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { revoke, store } from '@/routes/invitations';
import { deactivate, index, reactivate, role } from '@/routes/users';
import type { Role } from '@/types';

/*
 * UC-032: the org admin's user list (search, filter by role and status,
 * paginated), invitations, role changes and (de)activation.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'users.index.title', href: index() }],
    },
});

type UserRow = {
    id: string;
    name: string;
    nickname: string;
    email: string;
    role: Role;
    is_active: boolean;
};

type InvitationRow = {
    id: string;
    email: string;
    role: Role;
    expires_at: string;
    expired: boolean;
};

const props = defineProps<{
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    invitations: InvitationRow[];
    filters: { search: string; role: string; status: string };
    roles: Role[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);
const errors = computed(() => page.props.errors as Record<string, string>);

const search = ref(props.filters.search);
const roleFilter = ref(props.filters.role);
const statusFilter = ref(props.filters.status);

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

function applyFilters(): void {
    const query: Record<string, string> = {};

    if (search.value) query.search = search.value;
    if (roleFilter.value) query.role = roleFilter.value;
    if (statusFilter.value) query.status = statusFilter.value;

    router.get(
        index.url({ query }),
        {},
        { preserveState: true, replace: true },
    );
}

function changeRole(user: UserRow, event: Event): void {
    const value = (event.target as HTMLSelectElement).value as Role;

    router.patch(
        role.url(user.id),
        { role: value },
        {
            preserveScroll: true,
            onError: () => {
                (event.target as HTMLSelectElement).value = user.role;
            },
        },
    );
}

function setActive(user: UserRow, active: boolean): void {
    router.post(
        active ? reactivate.url(user.id) : deactivate.url(user.id),
        {},
        { preserveScroll: true },
    );
}

function revokeInvitation(invitation: InvitationRow): void {
    router.post(revoke.url(invitation.id), {}, { preserveScroll: true });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(document.documentElement.lang);
}
</script>

<template>
    <Head :title="$t('users.index.title')" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <div class="space-y-1">
            <h1 class="text-xl font-medium" data-test="users-title">
                {{ $t('users.index.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ $t('users.index.description') }}
            </p>
        </div>

        <section class="space-y-3 rounded-xl border p-4">
            <h2 class="font-medium">{{ $t('users.invite.title') }}</h2>
            <Form
                v-slot="{ errors: formErrors, processing }"
                v-bind="store.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="flex flex-col gap-3 md:flex-row md:items-end"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="invite-email">{{
                        $t('users.invite.email')
                    }}</Label>
                    <Input
                        id="invite-email"
                        type="email"
                        name="email"
                        required
                        autocomplete="off"
                        :placeholder="$t('ui.auth.email_placeholder')"
                    />
                    <InputError :message="formErrors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="invite-role">{{
                        $t('users.invite.role')
                    }}</Label>
                    <select
                        id="invite-role"
                        name="role"
                        :class="selectClass"
                        required
                    >
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ $t(`users.roles.${r}`) }}
                        </option>
                    </select>
                    <InputError :message="formErrors.role" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="invite-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('users.invite.submit') }}
                </Button>
            </Form>
        </section>

        <section class="space-y-3">
            <h2 class="font-medium">{{ $t('users.invitations.title') }}</h2>
            <InputError :message="errors.invitation" />
            <p
                v-if="invitations.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('users.invitations.empty') }}
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex flex-wrap items-center gap-3 p-3 text-sm"
                    data-test="invitation-row"
                >
                    <span class="flex-1 font-medium">{{
                        invitation.email
                    }}</span>
                    <Badge variant="secondary">{{
                        $t(`users.roles.${invitation.role}`)
                    }}</Badge>
                    <Badge v-if="invitation.expired" variant="destructive">{{
                        $t('users.invitations.expired')
                    }}</Badge>
                    <span v-else class="text-muted-foreground">
                        {{ $t('users.invitations.expires') }}
                        {{ formatDate(invitation.expires_at) }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="revokeInvitation(invitation)"
                    >
                        {{ $t('users.invitations.revoke') }}
                    </Button>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <form
                class="flex flex-col gap-3 md:flex-row md:items-end"
                @submit.prevent="applyFilters"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="search" class="sr-only">{{
                        $t('users.index.search')
                    }}</Label>
                    <Input
                        id="search"
                        v-model="search"
                        type="search"
                        :placeholder="$t('users.index.search')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="role-filter">{{
                        $t('users.index.filter_role')
                    }}</Label>
                    <select
                        id="role-filter"
                        v-model="roleFilter"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('users.index.all_roles') }}
                        </option>
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ $t(`users.roles.${r}`) }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="status-filter">{{
                        $t('users.index.filter_status')
                    }}</Label>
                    <select
                        id="status-filter"
                        v-model="statusFilter"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('users.index.all_statuses') }}
                        </option>
                        <option value="active">
                            {{ $t('users.statuses.active') }}
                        </option>
                        <option value="inactive">
                            {{ $t('users.statuses.inactive') }}
                        </option>
                    </select>
                </div>
                <Button type="submit" variant="secondary">
                    {{ $t('users.index.apply') }}
                </Button>
            </form>

            <InputError :message="errors.role ?? errors.user" />

            <p
                v-if="users.data.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('users.index.empty') }}
            </p>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="p-3 font-medium">
                                {{ $t('users.index.name') }}
                            </th>
                            <th class="p-3 font-medium">
                                {{ $t('users.index.email') }}
                            </th>
                            <th class="p-3 font-medium">
                                {{ $t('users.index.role') }}
                            </th>
                            <th class="p-3 font-medium">
                                {{ $t('users.index.status') }}
                            </th>
                            <th class="p-3 font-medium">
                                <span class="sr-only">{{
                                    $t('users.index.actions')
                                }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            data-test="user-row"
                        >
                            <td class="p-3">
                                <div class="font-medium">
                                    {{ user.name }}
                                    <span
                                        v-if="user.id === currentUserId"
                                        class="text-muted-foreground"
                                        >({{ $t('users.index.you') }})</span
                                    >
                                </div>
                                <div class="text-muted-foreground">
                                    {{ user.nickname }}
                                </div>
                            </td>
                            <td class="p-3">{{ user.email }}</td>
                            <td class="p-3">
                                <label :for="`role-${user.id}`" class="sr-only">
                                    {{ $t('users.index.change_role') }}
                                </label>
                                <select
                                    :id="`role-${user.id}`"
                                    :value="user.role"
                                    :class="selectClass"
                                    @change="changeRole(user, $event)"
                                >
                                    <option
                                        v-for="r in roles"
                                        :key="r"
                                        :value="r"
                                    >
                                        {{ $t(`users.roles.${r}`) }}
                                    </option>
                                </select>
                            </td>
                            <td class="p-3">
                                <Badge
                                    :variant="
                                        user.is_active ? 'secondary' : 'outline'
                                    "
                                >
                                    {{
                                        $t(
                                            user.is_active
                                                ? 'users.statuses.active'
                                                : 'users.statuses.inactive',
                                        )
                                    }}
                                </Badge>
                            </td>
                            <td class="p-3 text-right">
                                <Button
                                    v-if="user.is_active"
                                    variant="outline"
                                    size="sm"
                                    @click="setActive(user, false)"
                                >
                                    {{ $t('users.index.deactivate') }}
                                </Button>
                                <Button
                                    v-else
                                    variant="outline"
                                    size="sm"
                                    @click="setActive(user, true)"
                                >
                                    {{ $t('users.index.reactivate') }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="users.last_page > 1"
                class="flex items-center justify-between text-sm"
            >
                <Link
                    v-if="users.prev_page_url"
                    :href="users.prev_page_url"
                    preserve-scroll
                    class="underline"
                >
                    {{ $t('users.index.previous') }}
                </Link>
                <span v-else></span>
                <span class="text-muted-foreground">
                    {{
                        $t('users.index.page', {
                            current: String(users.current_page),
                            last: String(users.last_page),
                        })
                    }}
                </span>
                <Link
                    v-if="users.next_page_url"
                    :href="users.next_page_url"
                    preserve-scroll
                    class="underline"
                >
                    {{ $t('users.index.next') }}
                </Link>
                <span v-else></span>
            </nav>
        </section>
    </div>
</template>
