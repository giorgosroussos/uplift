<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import LiveAvatar from '@/components/live/LiveAvatar.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useLiveSync } from '@/composables/useLiveSync';
import {
    answeredPercentage,
    applyProgressUpdated,
    applySessionChanged,
    applySnapshot,
    clockOffsetMs,
    progressSummary,
    remainingSeconds,
} from '@/lib/liveMonitor';
import { LIVE_EVENTS, LIVE_POLL_INTERVAL_MS } from '@/lib/liveSync';
import { formatRemaining } from '@/lib/quizAttempt';
import {
    cancel as cancelRoute,
    start as startRoute,
    state as stateRoute,
} from '@/routes/live/sessions';
import type {
    LiveHostState,
    LiveMonitorQuestion,
    LiveProgressUpdatedPayload,
    LiveSessionChangedPayload,
} from '@/types/live';
import type { LiveStartMode } from '@/types/quiz';

/*
 * UC-024: the host's view of one live session. Lobby (who joined, Start
 * or close), live progress while it runs (who joined, how many questions
 * each answered, who submitted; never an answer, OWN-013) and the
 * results once finished. Progress broadcasts apply at once; snapshots
 * catch up after a reconnection and every 2 seconds while the websocket
 * is down (FLOW-002, ARCH-006). The countdown derives from the server's
 * ends_at and server_now. Closing the page changes nothing: the server
 * finishes the session at its end time.
 */
const props = defineProps<{
    initial: LiveHostState;
    startMode: LiveStartMode | null;
    scheduledStartAt: string | null;
    questions: LiveMonitorQuestion[];
}>();

const state = ref<LiveHostState>(props.initial);
const offsetMs = ref(0);
const now = ref(Date.now());
const busy = ref(false);

function syncClock(serverNow: string): void {
    now.value = Date.now();
    offsetMs.value = clockOffsetMs(serverNow, now.value);
}

syncClock(props.initial.progress.session.server_now);

// A fresh page snapshot (after Start) replaces the local state.
watch(
    () => props.initial,
    (snapshot) => {
        state.value = applySnapshot(state.value, snapshot);
        syncClock(snapshot.progress.session.server_now);
    },
);

const session = computed(() => state.value.progress.session);
const participants = computed(() => state.value.progress.participants);
const summary = computed(() => progressSummary(participants.value));
const results = computed(() => state.value.results);
const remaining = computed(() =>
    remainingSeconds(session.value.ends_at, offsetMs.value, now.value),
);
const orderedQuestionResults = computed(() => {
    const counts = new Map(
        (results.value?.questions ?? []).map((row) => [
            row.quiz_question_id,
            row,
        ]),
    );

    return props.questions.map((question, index) => ({
        question,
        number: index + 1,
        answered: counts.get(question.id)?.answered ?? 0,
        correct: counts.get(question.id)?.correct ?? 0,
    }));
});

const { mode, refresh } = useLiveSync<LiveHostState>({
    url: () => stateRoute(props.initial.progress.session.id).url,
    onSnapshot: (snapshot) => {
        state.value = applySnapshot(state.value, snapshot);
        syncClock(snapshot.progress.session.server_now);
    },
    subscribe: (echo) => {
        const { session: sessionChannel, host } = props.initial.channels;

        echo.private(sessionChannel).listen(
            LIVE_EVENTS.sessionChanged,
            (event: LiveSessionChangedPayload) => {
                state.value = applySessionChanged(state.value, event.session);
                syncClock(event.session.server_now);

                // Scores and ranks never travel over the socket (SCOPE-021).
                if (event.session.status === 'finished') {
                    void refresh();
                }
            },
        );
        echo.join(host).listen(
            LIVE_EVENTS.progressUpdated,
            (event: LiveProgressUpdatedPayload) => {
                state.value = applyProgressUpdated(
                    state.value,
                    event.session_id,
                    event.participant,
                );
            },
        );

        return () => {
            echo.leave(sessionChannel);
            echo.leave(host);
        };
    },
});

// The countdown ticks every second; once the end time has passed, the
// snapshot is fetched until the server reports the session finished.
let ticker: ReturnType<typeof setInterval> | null = null;
let lastCatchUp = 0;

onMounted(() => {
    ticker = setInterval(() => {
        now.value = Date.now();

        if (
            session.value.status === 'running' &&
            remaining.value === 0 &&
            now.value - lastCatchUp >= LIVE_POLL_INTERVAL_MS
        ) {
            lastCatchUp = now.value;
            void refresh();
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (ticker !== null) {
        clearInterval(ticker);
    }
});

function formatTime(value: string): string {
    return new Date(value).toLocaleString(
        document.documentElement.lang || undefined,
        {
            dateStyle: 'medium',
            timeStyle: 'medium',
        },
    );
}

function post(url: string): void {
    busy.value = true;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function start(): void {
    post(startRoute(session.value.id).url);
}

function closeLobby(): void {
    post(cancelRoute(session.value.id).url);
}
</script>

<template>
    <div class="flex flex-col gap-6" data-test="live-host-session">
        <div>
            <Badge
                variant="secondary"
                data-test="live-status"
                :data-status="session.status"
            >
                {{ $t(`live.monitor.statuses.${session.status}`) }}
            </Badge>
        </div>

        <Alert
            v-if="
                mode === 'polling' &&
                (session.status === 'lobby' || session.status === 'running')
            "
            data-test="live-reconnecting"
        >
            <AlertDescription>{{
                $t('live.monitor.reconnecting')
            }}</AlertDescription>
        </Alert>

        <!-- Lobby: who joined; Start (manual) or the scheduled time. -->
        <section
            v-if="session.status === 'lobby'"
            class="flex flex-col gap-4"
            data-test="live-lobby"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-medium">
                    {{ $t('live.monitor.lobby_title') }}
                </h2>
                <span class="text-sm" data-test="live-joined-count">{{
                    $t('live.monitor.joined_count', {
                        count: String(summary.joined),
                    })
                }}</span>
            </div>

            <p
                v-if="participants.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('live.monitor.lobby_waiting') }}
            </p>
            <ul v-else class="flex flex-wrap gap-2">
                <li
                    v-for="participant in participants"
                    :key="participant.id"
                    class="flex items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm"
                    data-test="live-lobby-participant"
                >
                    <LiveAvatar :student="participant.student" />
                    {{ participant.student.nickname }}
                </li>
            </ul>

            <p
                v-if="startMode === 'scheduled' && scheduledStartAt"
                class="text-sm"
                data-test="live-starts-at"
            >
                {{
                    $t('live.monitor.starts_at', {
                        time: formatTime(scheduledStartAt),
                    })
                }}
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="startMode !== 'scheduled'"
                    :disabled="busy"
                    data-test="live-start"
                    @click="start"
                >
                    {{ $t('live.monitor.start') }}
                </Button>
                <Button
                    variant="outline"
                    :disabled="busy"
                    data-test="live-close-lobby"
                    @click="closeLobby"
                >
                    {{ $t('live.monitor.close_lobby') }}
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ $t('live.monitor.close_lobby_hint') }}
            </p>
        </section>

        <!-- Running: live progress, never answers or correctness (OWN-013). -->
        <section
            v-if="session.status === 'running'"
            class="flex flex-col gap-4"
            data-test="live-progress"
        >
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <span class="text-sm text-muted-foreground">{{
                        $t('live.monitor.time_left')
                    }}</span>
                    <span
                        class="font-mono text-3xl tabular-nums"
                        role="timer"
                        aria-live="off"
                        data-test="live-countdown"
                        >{{ formatRemaining(remaining ?? 0) }}</span
                    >
                    <span
                        v-if="session.ends_at"
                        class="text-xs text-muted-foreground"
                        data-test="live-ends-at"
                        :data-ends-at="session.ends_at"
                        >{{
                            $t('live.monitor.ends_at', {
                                time: formatTime(session.ends_at),
                            })
                        }}</span
                    >
                </div>
                <div class="flex flex-col items-end gap-1 text-sm">
                    <span data-test="live-joined-count">{{
                        $t('live.monitor.joined_count', {
                            count: String(summary.joined),
                        })
                    }}</span>
                    <span data-test="live-submitted-count">{{
                        $t('live.monitor.submitted_count', {
                            submitted: String(summary.submitted),
                            joined: String(summary.joined),
                        })
                    }}</span>
                </div>
            </div>

            <p
                v-if="remaining === 0"
                class="text-sm text-muted-foreground"
                data-test="live-finishing"
            >
                {{ $t('live.monitor.finishing') }}
            </p>

            <p
                v-if="participants.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('live.monitor.no_participants') }}
            </p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-2 pr-4 font-normal">
                                {{ $t('live.monitor.student') }}
                            </th>
                            <th class="py-2 pr-4 font-normal">
                                {{ $t('live.monitor.answered') }}
                            </th>
                            <th class="py-2 font-normal">
                                {{ $t('live.monitor.state') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="participant in participants"
                            :key="participant.id"
                            class="border-t"
                            data-test="live-progress-row"
                            :data-participant="participant.id"
                        >
                            <td class="py-2 pr-4">
                                <span class="flex items-center gap-2">
                                    <LiveAvatar
                                        :student="participant.student"
                                    />
                                    {{ participant.student.nickname }}
                                </span>
                            </td>
                            <td class="py-2 pr-4">
                                <div class="flex min-w-32 flex-col gap-1">
                                    <span data-test="live-progress-answered">{{
                                        $t('live.monitor.answered_of', {
                                            answered: String(
                                                participant.answered,
                                            ),
                                            total: String(
                                                session.question_count,
                                            ),
                                        })
                                    }}</span>
                                    <div
                                        class="h-1.5 w-full rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-primary transition-[width]"
                                            :style="{
                                                width: `${answeredPercentage(participant.answered, session.question_count)}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </td>
                            <td class="py-2">
                                <Badge
                                    :variant="
                                        participant.submitted
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                    data-test="live-progress-state"
                                >
                                    {{
                                        participant.submitted
                                            ? $t('live.monitor.submitted')
                                            : $t('live.monitor.in_progress')
                                    }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Finished: ranking and podium only with leaderboards enabled. -->
        <section
            v-if="session.status === 'finished' && results"
            class="flex flex-col gap-6"
            data-test="live-results"
        >
            <h2 class="text-lg font-medium">
                {{ $t('live.monitor.results_title') }}
            </h2>

            <div
                v-if="results.leaderboard && results.podium"
                class="flex flex-col gap-2"
                data-test="live-podium"
            >
                <h3 class="font-medium">{{ $t('live.monitor.podium') }}</h3>
                <ol class="flex flex-wrap gap-3">
                    <li
                        v-for="entry in results.podium"
                        :key="entry.student.id"
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        data-test="live-podium-entry"
                    >
                        <span class="text-lg font-semibold tabular-nums">{{
                            entry.rank
                        }}</span>
                        <LiveAvatar :student="entry.student" />
                        {{ entry.student.nickname }}
                    </li>
                </ol>
            </div>

            <Alert v-if="!results.leaderboard" data-test="live-no-leaderboard">
                <AlertDescription>{{
                    $t('live.monitor.leaderboard_disabled')
                }}</AlertDescription>
            </Alert>

            <div class="flex flex-col gap-2">
                <h3 class="font-medium">
                    {{
                        results.leaderboard
                            ? $t('live.monitor.ranking')
                            : $t('live.monitor.scores')
                    }}
                </h3>
                <p
                    v-if="results.participants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('live.monitor.no_participants') }}
                </p>
                <div v-else class="overflow-x-auto">
                    <table
                        class="w-full text-sm"
                        :data-test="
                            results.leaderboard ? 'live-ranking' : 'live-scores'
                        "
                    >
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th
                                    v-if="results.leaderboard"
                                    class="py-2 pr-4 font-normal"
                                >
                                    {{ $t('live.monitor.rank') }}
                                </th>
                                <th class="py-2 pr-4 font-normal">
                                    {{ $t('live.monitor.student') }}
                                </th>
                                <th class="py-2 font-normal">
                                    {{ $t('live.monitor.score') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="participant in results.participants"
                                :key="participant.id"
                                class="border-t"
                                data-test="live-result-row"
                            >
                                <td
                                    v-if="results.leaderboard"
                                    class="py-2 pr-4 tabular-nums"
                                    data-test="live-result-rank"
                                >
                                    {{ participant.final_rank }}
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="flex items-center gap-2">
                                        <LiveAvatar
                                            :student="participant.student"
                                        />
                                        {{ participant.student.nickname }}
                                    </span>
                                </td>
                                <td
                                    class="py-2 tabular-nums"
                                    data-test="live-result-score"
                                >
                                    {{ participant.score }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="questions.length > 0" class="flex flex-col gap-2">
                <h3 class="font-medium">
                    {{ $t('live.monitor.questions_title') }}
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-2 pr-4 font-normal">
                                    {{ $t('live.monitor.question') }}
                                </th>
                                <th class="py-2 pr-4 font-normal">
                                    {{ $t('live.monitor.answered_by') }}
                                </th>
                                <th class="py-2 font-normal">
                                    {{ $t('live.monitor.correct_by') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in orderedQuestionResults"
                                :key="row.question.id"
                                class="border-t"
                                data-test="live-question-row"
                            >
                                <td class="max-w-md py-2 pr-4">
                                    <span class="line-clamp-2">{{
                                        $t('live.monitor.question_label', {
                                            position: String(row.number),
                                            prompt: row.question.prompt,
                                        })
                                    }}</span>
                                </td>
                                <td class="py-2 pr-4 tabular-nums">
                                    {{ row.answered }}
                                </td>
                                <td class="py-2 tabular-nums">
                                    {{ row.correct }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
