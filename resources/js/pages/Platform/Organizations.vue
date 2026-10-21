<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
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
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { index, reactivate, suspend } from '@/routes/platform/organizations';

/*
 * UC-036: the platform admin's list of all organisations (search, filter by
 * status, paginated) with suspend and reactivate (SEC-006).
 */
defineOptions({ layout: PlatformLayout });

type Status = 'active' | 'suspended';

type OrganizationRow = {
    id: string;
    name: string;
    subdomain: string;
    status: Status;
    users_count: number;
    created_at: string | null;
};

const props = defineProps<{
    organizations: {
        data: OrganizationRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string; status: string };
    statuses: Status[];
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const search = ref(props.filters.search);
const statusFilter = ref(props.filters.status);
const confirming = ref<OrganizationRow | null>(null);

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

function applyFilters(): void {
    const query: Record<string, string> = {};

    if (search.value) query.search = search.value;
    if (statusFilter.value) query.status = statusFilter.value;

    router.get(
        index.url({ query }),
        {},
        { preserveState: true, replace: true },
    );
}

function confirmChange(): void {
    const organization = confirming.value;

    if (organization === null) return;

    router.post(
        organization.status === 'active'
            ? suspend.url(organization.id)
            : reactivate.url(organization.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                confirming.value = null;
            },
        },
    );
}

function formatDate(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleDateString(document.documentElement.lang)
        : '';
}
</script>

<template>
    <Head :title="$t('platform.organizations.title')" />

    <div class="flex flex-col gap-6">
        <div class="space-y-1">
            <h1 class="text-xl font-medium" data-test="organizations-title">
                {{ $t('platform.organizations.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ $t('platform.organizations.description') }}
            </p>
        </div>

        <form
            class="flex flex-col gap-3 md:flex-row md:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid flex-1 gap-2">
                <Label for="search" class="sr-only">{{
                    $t('platform.organizations.search')
                }}</Label>
                <Input
                    id="search"
                    v-model="search"
                    type="search"
                    :placeholder="$t('platform.organizations.search')"
                />
            </div>
            <div class="grid gap-2">
                <Label for="status" class="sr-only">{{
                    $t('platform.organizations.status')
                }}</Label>
                <select id="status" v-model="statusFilter" :class="selectClass">
                    <option value="">
                        {{ $t('platform.organizations.all_statuses') }}
                    </option>
                    <option v-for="s in statuses" :key="s" :value="s">
                        {{ $t(`platform.statuses.${s}`) }}
                    </option>
                </select>
            </div>
            <Button type="submit" variant="outline" data-test="filter-button">
                {{ $t('platform.organizations.filter') }}
            </Button>
        </form>

        <InputError :message="errors.organization" />

        <p
            v-if="organizations.data.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('platform.organizations.empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="text-left text-muted-foreground">
                    <tr class="border-b">
                        <th class="p-3 font-medium">
                            {{ $t('platform.organizations.name') }}
                        </th>
                        <th class="p-3 font-medium">
                            {{ $t('platform.organizations.subdomain') }}
                        </th>
                        <th class="p-3 font-medium">
                            {{ $t('platform.organizations.status') }}
                        </th>
                        <th class="p-3 text-right font-medium">
                            {{ $t('platform.organizations.users') }}
                        </th>
                        <th class="p-3 font-medium">
                            {{ $t('platform.organizations.created') }}
                        </th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="organization in organizations.data"
                        :key="organization.id"
                        data-test="organization-row"
                    >
                        <td class="p-3 font-medium">{{ organization.name }}</td>
                        <td class="p-3">{{ organization.subdomain }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    organization.status === 'active'
                                        ? 'secondary'
                                        : 'destructive'
                                "
                                data-test="organization-status"
                                >{{
                                    $t(
                                        `platform.statuses.${organization.status}`,
                                    )
                                }}</Badge
                            >
                        </td>
                        <td class="p-3 text-right">
                            {{ organization.users_count }}
                        </td>
                        <td class="p-3">
                            {{ formatDate(organization.created_at) }}
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                :data-test="
                                    organization.status === 'active'
                                        ? 'organization-suspend'
                                        : 'organization-reactivate'
                                "
                                @click="confirming = organization"
                            >
                                {{
                                    organization.status === 'active'
                                        ? $t('platform.organizations.suspend')
                                        : $t(
                                              'platform.organizations.reactivate',
                                          )
                                }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="organizations.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!organizations.prev_page_url"
                @click="
                    organizations.prev_page_url &&
                    router.get(organizations.prev_page_url)
                "
            >
                {{ $t('platform.organizations.previous') }}
            </Button>
            <span class="text-muted-foreground">
                {{
                    $t('platform.organizations.page', {
                        current: String(organizations.current_page),
                        last: String(organizations.last_page),
                    })
                }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!organizations.next_page_url"
                @click="
                    organizations.next_page_url &&
                    router.get(organizations.next_page_url)
                "
            >
                {{ $t('platform.organizations.next') }}
            </Button>
        </div>

        <Dialog
            :open="confirming !== null"
            @update:open="(open: boolean) => !open && (confirming = null)"
        >
            <DialogContent data-test="organization-status-dialog">
                <DialogTitle>
                    {{
                        $t(
                            confirming?.status === 'active'
                                ? 'platform.organizations.confirm_suspend_title'
                                : 'platform.organizations.confirm_reactivate_title',
                            { name: confirming?.name ?? '' },
                        )
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            confirming?.status === 'active'
                                ? 'platform.organizations.confirm_suspend_body'
                                : 'platform.organizations.confirm_reactivate_body',
                        )
                    }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('platform.organizations.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        :variant="
                            confirming?.status === 'active'
                                ? 'destructive'
                                : 'default'
                        "
                        data-test="organization-status-confirm"
                        @click="confirmChange"
                    >
                        {{
                            confirming?.status === 'active'
                                ? $t('platform.organizations.suspend')
                                : $t('platform.organizations.reactivate')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
