<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { canAct, defaultGroup } from '@/lib/slotForm';
import { edit, index, show as showActivity } from '@/routes/activities';
import {
    book as bookRoute,
    cancel as cancelRoute,
} from '@/routes/activities/slots';
import type { SlotBookingDetails, SlotGroup, SlotRow } from '@/types/slots';

/*
 * UC-016: a slot booking as a student (book, move or cancel a slot until
 * the deadline while it has not started, alone or for one of their
 * groups) or its teacher (who holds which slot, OWN-010) sees it.
 * Everybody sees the remaining places of every slot; students never see
 * who holds other bookings (SCOPE-028).
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<{
    slotBooking: SlotBookingDetails;
    booking: { slot_id: string } | null;
    groups: SlotGroup[];
    can: { book: boolean; viewHolders: boolean; update: boolean };
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
const currentSlotId = computed(() =>
    props.slotBooking.is_group
        ? (selectedGroup.value?.slot_id ?? null)
        : (props.booking?.slot_id ?? null),
);
const currentSlot = computed(
    () =>
        props.slotBooking.slots.find(
            (slot) => slot.id === currentSlotId.value,
        ) ?? null,
);
const mayBook = computed(
    () =>
        props.can.book &&
        !(currentSlot.value?.started ?? false) &&
        (!props.slotBooking.is_group ||
            (selectedGroup.value !== null && canAct(selectedGroup.value))),
);

function formatDate(value: string): string {
    return new Date(value).toLocaleString(document.documentElement.lang, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString(document.documentElement.lang, {
        timeStyle: 'short',
    });
}

function slotLabel(slot: SlotRow | null): string | null {
    if (!slot) {
        return null;
    }

    return `${formatDate(slot.starts_at)} – ${formatTime(slot.ends_at)}`;
}

function slotById(id: string | null): SlotRow | null {
    return props.slotBooking.slots.find((slot) => slot.id === id) ?? null;
}

function groupPayload(): Record<string, string> {
    return props.slotBooking.is_group && actingGroup.value
        ? { group_id: actingGroup.value }
        : {};
}

function book(slotId: string): void {
    submitting.value = true;
    router.put(
        bookRoute(props.slotBooking.activity_id).url,
        { slot_id: slotId, ...groupPayload() },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function cancelBooking(): void {
    submitting.value = true;
    router.delete(cancelRoute(props.slotBooking.activity_id).url, {
        data: groupPayload(),
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <Head :title="slotBooking.title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="slots-title">
                    {{ slotBooking.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{{
                        $t('activities.types.slot_booking')
                    }}</Badge>
                    <Badge variant="outline" data-test="slots-status">
                        {{ $t(`activities.statuses.${slotBooking.status}`) }}
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="showActivity(slotBooking.activity_id)">
                        {{ $t('activities.wizard.back') }}
                    </Link>
                </Button>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(slotBooking.activity_id)">
                        {{ $t('activities.show.edit') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p v-if="slotBooking.description" class="text-sm whitespace-pre-line">
            {{ slotBooking.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('activities.show.course_space') }}
            </dt>
            <dd>{{ slotBooking.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('activities.show.deadline') }}
            </dt>
            <dd>
                {{
                    slotBooking.deadline_at
                        ? formatDate(slotBooking.deadline_at)
                        : $t('activities.show.no_deadline')
                }}
            </dd>
        </dl>

        <p
            v-if="slotBooking.is_group"
            class="text-sm text-muted-foreground"
            data-test="slots-group-activity"
        >
            {{
                $t('activities.slots.show.group_activity', {
                    size: String(slotBooking.group_size ?? ''),
                })
            }}
        </p>

        <Alert
            v-if="!slotBooking.is_open && slotBooking.status !== 'draft'"
            data-test="slots-closed"
        >
            <AlertDescription>{{
                $t('activities.slots.show.closed')
            }}</AlertDescription>
        </Alert>

        <!-- The student's own booking, or their groups' bookings (OWN-010). -->
        <section
            v-if="
                !can.viewHolders &&
                !slotBooking.is_group &&
                (can.book || booking)
            "
            class="flex flex-col gap-2 rounded-md border p-4"
            data-test="slots-my-booking"
        >
            <h2 class="text-sm font-medium">
                {{ $t('activities.slots.show.your_booking') }}
            </h2>
            <p class="text-sm" data-test="slots-current">
                {{
                    slotLabel(slotById(booking?.slot_id ?? null)) ??
                    $t('activities.slots.show.no_booking')
                }}
            </p>
        </section>

        <section
            v-if="
                !can.viewHolders &&
                slotBooking.is_group &&
                slotBooking.status !== 'draft'
            "
            class="flex flex-col gap-3 rounded-md border p-4"
            data-test="slots-groups"
        >
            <Alert v-if="groups.length === 0" data-test="slots-no-groups">
                <AlertDescription>{{
                    $t('activities.slots.show.no_groups')
                }}</AlertDescription>
            </Alert>
            <template v-else>
                <ul class="flex flex-col gap-1 text-sm">
                    <li
                        v-for="(group, position) in groups"
                        :key="group.id"
                        :data-test="`slots-group-booking-${position}`"
                    >
                        <span v-if="group.slot_id">
                            {{
                                $t('activities.slots.show.group_booking', {
                                    group: group.name,
                                })
                            }}:
                            <strong>{{
                                slotLabel(slotById(group.slot_id))
                            }}</strong>
                        </span>
                        <span v-else class="text-muted-foreground">
                            {{
                                $t('activities.slots.show.group_no_booking', {
                                    group: group.name,
                                })
                            }}
                        </span>
                    </li>
                </ul>
                <div v-if="can.book" class="grid gap-2">
                    <Label for="acting-group">{{
                        $t('activities.slots.show.acting_group')
                    }}</Label>
                    <select
                        id="acting-group"
                        v-model="actingGroup"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                        data-test="slots-acting-group"
                    >
                        <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{
                                $t('activities.slots.show.group_option', {
                                    name: group.name,
                                    members: String(group.members),
                                })
                            }}
                        </option>
                    </select>
                    <p
                        v-if="selectedGroup?.too_large"
                        class="text-sm text-muted-foreground"
                        data-test="slots-group-too-large"
                    >
                        {{ $t('activities.slots.show.group_too_large') }}
                    </p>
                    <p
                        v-else-if="selectedGroup?.in_other_group"
                        class="text-sm text-muted-foreground"
                        data-test="slots-group-in-other"
                    >
                        {{ $t('activities.slots.show.group_in_other') }}
                    </p>
                </div>
            </template>
        </section>

        <p v-if="can.book" class="text-xs text-muted-foreground">
            {{ $t('activities.slots.show.deadline_hint') }}
        </p>
        <InputError :message="errors.slot" />
        <InputError :message="errors.slot_id" />
        <InputError :message="errors.group_id" />

        <p
            v-if="slotBooking.slots.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('activities.slots.show.no_slots') }}
        </p>

        <ul class="flex flex-col gap-3" data-test="slots-list">
            <li
                v-for="(slot, position) in slotBooking.slots"
                :key="slot.id"
                class="flex flex-col gap-2 rounded-md border p-4"
                :data-test="`slot-row-${position}`"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-col gap-1">
                        <span
                            class="font-medium"
                            :data-test="`slot-time-${position}`"
                            >{{ slotLabel(slot) }}</span
                        >
                        <span
                            v-if="slot.location"
                            class="text-sm"
                            :data-test="`slot-location-${position}`"
                        >
                            <span class="text-muted-foreground"
                                >{{
                                    $t('activities.slots.show.location')
                                }}:</span
                            >
                            {{ slot.location }}
                        </span>
                        <span
                            class="text-sm text-muted-foreground"
                            :data-test="`slot-remaining-${position}`"
                        >
                            {{
                                $tChoice(
                                    'activities.slots.show.remaining',
                                    slot.remaining,
                                    { capacity: String(slot.capacity) },
                                )
                            }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="slot.started"
                            variant="outline"
                            :data-test="`slot-started-${position}`"
                        >
                            {{ $t('activities.slots.show.started') }}
                        </Badge>
                        <Badge
                            v-if="currentSlotId === slot.id"
                            :data-test="`slot-booked-${position}`"
                        >
                            {{ $t('activities.slots.show.booked') }}
                        </Badge>
                        <Button
                            v-else-if="
                                mayBook && !slot.started && slot.remaining > 0
                            "
                            size="sm"
                            :variant="currentSlotId ? 'outline' : 'default'"
                            :disabled="submitting"
                            :data-test="`slot-book-${position}`"
                            @click="book(slot.id)"
                        >
                            {{
                                currentSlotId
                                    ? $t('activities.slots.show.switch')
                                    : $t('activities.slots.show.book')
                            }}
                        </Button>
                    </div>
                </div>
                <p
                    v-if="can.viewHolders"
                    class="text-sm"
                    :data-test="`slot-holders-${position}`"
                >
                    <span class="text-muted-foreground"
                        >{{ $t('activities.slots.show.holders') }}:</span
                    >
                    {{
                        slot.holders && slot.holders.length > 0
                            ? slot.holders.join(', ')
                            : $t('activities.slots.show.no_holders')
                    }}
                </p>
            </li>
        </ul>

        <div v-if="can.book && currentSlot && !currentSlot.started">
            <Button
                variant="destructive"
                :disabled="submitting"
                data-test="slots-cancel"
                @click="cancelBooking"
            >
                {{
                    slotBooking.is_group
                        ? $t('activities.slots.show.cancel_group')
                        : $t('activities.slots.show.cancel')
                }}
            </Button>
        </div>
    </div>
</template>
