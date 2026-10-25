import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import HostSession from '@/components/live/HostSession.vue';
import type { LiveHostState, LiveProgressRow } from '@/types/live';
import {
    answeredPercentage,
    applyProgressUpdated,
    applySessionChanged,
    applySnapshot,
    clockOffsetMs,
    mergeProgressRow,
    mergeProgressRows,
    progressSummary,
    remainingSeconds,
} from './liveMonitor';
import { LIVE_EVENTS } from './liveSync';

const router = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router }));

vi.mock('@/routes/live/sessions', () => ({
    state: (id: string) => ({ url: `/live/sessions/${id}/state` }),
    start: (id: string) => ({ url: `/live/sessions/${id}/start` }),
    cancel: (id: string) => ({ url: `/live/sessions/${id}/cancel` }),
}));

function row(
    id: string,
    answered = 0,
    submitted = false,
    joinedAt = '2026-11-02T10:00:00+00:00',
): LiveProgressRow {
    return {
        id,
        student: { id: `s-${id}`, nickname: `nick-${id}`, avatar: null },
        joined_at: joinedAt,
        answered,
        submitted,
    };
}

function hostState(overrides: Partial<LiveHostState> = {}): LiveHostState {
    return {
        channels: {
            session: 'organization.org.live-session.session-1',
            host: 'organization.org.live-session.session-1.host',
        },
        progress: {
            session: {
                id: 'session-1',
                quiz_id: 'quiz-1',
                status: 'running',
                started_at: '2026-11-02T10:00:00+00:00',
                ends_at: '2026-11-02T10:15:00+00:00',
                server_now: '2026-11-02T10:00:00+00:00',
                question_count: 4,
            },
            participants: [],
        },
        results: null,
        ...overrides,
    };
}

describe('progress merging (UC-024)', () => {
    it('adds a joining participant in join order', () => {
        const rows = mergeProgressRow(
            [row('b', 0, false, '2026-11-02T10:00:02+00:00')],
            row('a', 0, false, '2026-11-02T10:00:01+00:00'),
        );

        expect(rows.map((r) => r.id)).toEqual(['a', 'b']);
    });

    it('updates the answered count and the submission of a participant', () => {
        let rows = [row('a', 1)];
        rows = mergeProgressRow(rows, row('a', 2));
        rows = mergeProgressRow(rows, row('a', 2, true));

        expect(rows).toEqual([row('a', 2, true)]);
    });

    it('never goes back when a stale row or snapshot arrives late', () => {
        const rows = mergeProgressRow([row('a', 3, true)], row('a', 2));
        expect(rows[0].answered).toBe(3);
        expect(rows[0].submitted).toBe(true);

        const merged = mergeProgressRows(
            [row('a', 3), row('b', 1)],
            [row('a', 1)],
        );
        expect(merged.map((r) => [r.id, r.answered])).toEqual([
            ['a', 3],
            ['b', 1],
        ]);
    });

    it('ignores broadcasts of another session', () => {
        const state = hostState();

        expect(applyProgressUpdated(state, 'other', row('a', 1))).toBe(state);
        expect(
            applySessionChanged(state, {
                ...state.progress.session,
                id: 'other',
                status: 'finished',
            }),
        ).toBe(state);
    });

    it('applies a status change with the common end time', () => {
        const lobby = hostState();
        lobby.progress.session = {
            ...lobby.progress.session,
            status: 'lobby',
            started_at: null,
            ends_at: null,
        };

        const running = applySessionChanged(lobby, {
            id: 'session-1',
            status: 'running',
            started_at: '2026-11-02T10:00:00+00:00',
            ends_at: '2026-11-02T10:15:00+00:00',
            server_now: '2026-11-02T10:00:00+00:00',
        });

        expect(running.progress.session.status).toBe('running');
        expect(running.progress.session.ends_at).toBe(
            '2026-11-02T10:15:00+00:00',
        );
        expect(running.progress.session.question_count).toBe(4);
    });

    it('takes the results of a later snapshot', () => {
        const finished = hostState({
            results: {
                leaderboard: true,
                ranking: [],
                podium: [],
                participants: [],
                questions: [],
            },
        });

        expect(applySnapshot(hostState(), finished).results).not.toBeNull();
    });

    it('counts joined and submitted participants', () => {
        expect(progressSummary([row('a', 1, true), row('b', 0)])).toEqual({
            joined: 2,
            submitted: 1,
        });
        expect(answeredPercentage(1, 4)).toBe(25);
        expect(answeredPercentage(0, 0)).toBe(0);
        expect(answeredPercentage(9, 4)).toBe(100);
    });
});

describe('countdown from the server clock (ARCH-006)', () => {
    it('started at 10:00:00 with 15 minutes ends at 10:15:00 whatever the browser clock says', () => {
        // The browser clock runs 7 minutes behind the server.
        const browserNow = Date.parse('2026-11-02T09:53:00Z');
        const offset = clockOffsetMs('2026-11-02T10:00:00+00:00', browserNow);

        expect(
            remainingSeconds('2026-11-02T10:15:00+00:00', offset, browserNow),
        ).toBe(15 * 60);
        expect(
            remainingSeconds(
                '2026-11-02T10:15:00+00:00',
                offset,
                browserNow + 15 * 60 * 1000,
            ),
        ).toBe(0);
        expect(
            remainingSeconds(
                '2026-11-02T10:15:00+00:00',
                offset,
                browserNow + 20 * 60 * 1000,
            ),
        ).toBe(0);
    });

    it('has no countdown before the start', () => {
        expect(remainingSeconds(null, 0, Date.now())).toBeNull();
    });
});

type Listener = (payload: unknown) => void;

function fakeEcho() {
    const listeners = new Map<string, Map<string, Listener>>();
    const channel = (name: string) => ({
        listen(event: string, callback: Listener) {
            if (!listeners.has(name)) {
                listeners.set(name, new Map());
            }

            listeners.get(name)!.set(event, callback);

            return this;
        },
    });

    return {
        private: vi.fn((name: string) => channel(`private-${name}`)),
        join: vi.fn((name: string) => channel(`presence-${name}`)),
        leave: vi.fn(),
        connector: {
            pusher: {
                connection: {
                    state: 'connected',
                    bind: vi.fn(),
                    unbind: vi.fn(),
                },
            },
        },
        emit(channelName: string, event: string, payload: unknown) {
            listeners.get(channelName)?.get(event)?.(payload);
        },
    };
}

const global = {
    mocks: {
        $t: (key: string, params?: Record<string, string>) =>
            params ? `${key} ${JSON.stringify(params)}` : key,
    },
};

describe('host monitor view (UC-024)', () => {
    let echo: ReturnType<typeof fakeEcho>;
    let fetchMock: ReturnType<typeof vi.fn>;
    let snapshot: LiveHostState;

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-11-02T10:00:00Z'));
        echo = fakeEcho();
        (window as unknown as { Echo: unknown }).Echo = echo;
        snapshot = hostState();
        fetchMock = vi.fn(async () => ({
            ok: true,
            json: async () => snapshot,
        }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
        delete (window as unknown as { Echo?: unknown }).Echo;
        router.post.mockReset();
    });

    function mountView(initial: LiveHostState, startMode = 'manual') {
        return mount(HostSession, {
            props: {
                initial,
                startMode: startMode as 'manual' | 'scheduled',
                scheduledStartAt: null,
                questions: [
                    {
                        id: 'qq-1',
                        position: 0,
                        prompt: 'First?',
                        points: '3.00',
                    },
                    {
                        id: 'qq-2',
                        position: 1,
                        prompt: 'Second?',
                        points: '1.00',
                    },
                ],
            },
            global,
        });
    }

    it('subscribes to the session and host channels of the snapshot', () => {
        const wrapper = mountView(hostState());

        expect(echo.private).toHaveBeenCalledWith(
            'organization.org.live-session.session-1',
        );
        expect(echo.join).toHaveBeenCalledWith(
            'organization.org.live-session.session-1.host',
        );

        wrapper.unmount();
        expect(echo.leave).toHaveBeenCalledWith(
            'organization.org.live-session.session-1',
        );
        expect(echo.leave).toHaveBeenCalledWith(
            'organization.org.live-session.session-1.host',
        );
    });

    it('with 50 joined students shows an answer as soon as its broadcast arrives, without a request', async () => {
        const fifty = Array.from({ length: 50 }, (_, i) =>
            row(`p${String(i).padStart(2, '0')}`, 0),
        );
        const initial = hostState();
        initial.progress.participants = fifty;
        snapshot = initial;
        const wrapper = mountView(initial);
        await vi.advanceTimersByTimeAsync(0);
        fetchMock.mockClear();

        expect(wrapper.findAll('[data-test="live-progress-row"]')).toHaveLength(
            50,
        );

        echo.emit(
            'presence-organization.org.live-session.session-1.host',
            LIVE_EVENTS.progressUpdated,
            { session_id: 'session-1', participant: row('p37', 1) },
        );
        await nextTick();

        const answered = wrapper
            .find('[data-participant="p37"]')
            .find('[data-test="live-progress-answered"]');
        expect(answered.text()).toContain('"answered":"1"');
        expect(answered.text()).toContain('"total":"4"');
        expect(fetchMock).not.toHaveBeenCalled();

        echo.emit(
            'presence-organization.org.live-session.session-1.host',
            LIVE_EVENTS.progressUpdated,
            { session_id: 'session-1', participant: row('p37', 4, true) },
        );
        await nextTick();

        expect(
            wrapper
                .find('[data-participant="p37"]')
                .find('[data-test="live-progress-state"]')
                .text(),
        ).toBe('live.monitor.submitted');
        expect(
            wrapper.find('[data-test="live-submitted-count"]').text(),
        ).toContain('"submitted":"1","joined":"50"');
    });

    it('shows the common end time and counts down from the server clock', async () => {
        const wrapper = mountView(hostState());

        expect(
            wrapper
                .find('[data-test="live-ends-at"]')
                .attributes('data-ends-at'),
        ).toBe('2026-11-02T10:15:00+00:00');
        expect(wrapper.find('[data-test="live-countdown"]').text()).toBe(
            '15:00',
        );

        await vi.advanceTimersByTimeAsync(61_000);

        expect(wrapper.find('[data-test="live-countdown"]').text()).toBe(
            '13:59',
        );
    });

    it('moves from the lobby to the progress view when the session starts (scheduled, no teacher action)', async () => {
        const lobby = hostState();
        lobby.progress.session = {
            ...lobby.progress.session,
            status: 'lobby',
            started_at: null,
            ends_at: null,
        };
        lobby.progress.participants = [row('a')];
        snapshot = lobby;
        const wrapper = mountView(lobby, 'scheduled');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.find('[data-test="live-lobby"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="live-start"]').exists()).toBe(false);

        echo.emit(
            'private-organization.org.live-session.session-1',
            LIVE_EVENTS.sessionChanged,
            {
                session: {
                    id: 'session-1',
                    status: 'running',
                    started_at: '2026-11-02T10:00:00+00:00',
                    ends_at: '2026-11-02T10:15:00+00:00',
                    server_now: '2026-11-02T10:00:00+00:00',
                },
            },
        );
        await nextTick();

        expect(wrapper.find('[data-test="live-lobby"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="live-progress"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="live-status"]').attributes('data-status'),
        ).toBe('running');
    });

    it('starts and closes a manual lobby through the HTTP endpoints', () => {
        const lobby = hostState();
        lobby.progress.session = {
            ...lobby.progress.session,
            status: 'lobby',
            started_at: null,
            ends_at: null,
        };
        const wrapper = mountView(lobby);

        void wrapper.find('[data-test="live-start"]').trigger('click');
        void wrapper.find('[data-test="live-close-lobby"]').trigger('click');

        expect(router.post.mock.calls.map((call) => call[0])).toEqual([
            '/live/sessions/session-1/start',
            '/live/sessions/session-1/cancel',
        ]);
    });

    it('fetches the results when the session finishes (none travel over the socket)', async () => {
        const wrapper = mountView(hostState());
        await vi.advanceTimersByTimeAsync(0);
        fetchMock.mockClear();

        snapshot = hostState({
            results: {
                leaderboard: true,
                ranking: [
                    { rank: 1, student: row('a').student },
                    { rank: 2, student: row('b').student },
                ],
                podium: [
                    { rank: 1, student: row('a').student },
                    { rank: 2, student: row('b').student },
                ],
                participants: [
                    {
                        id: 'a',
                        student: row('a').student,
                        score: '3.00',
                        final_rank: 1,
                    },
                    {
                        id: 'b',
                        student: row('b').student,
                        score: '2.00',
                        final_rank: 2,
                    },
                ],
                questions: [
                    { quiz_question_id: 'qq-1', answered: 2, correct: 1 },
                ],
            },
        });
        snapshot.progress.session.status = 'finished';

        echo.emit(
            'private-organization.org.live-session.session-1',
            LIVE_EVENTS.sessionChanged,
            {
                session: {
                    ...snapshot.progress.session,
                    status: 'finished',
                },
            },
        );
        await vi.advanceTimersByTimeAsync(0);

        expect(fetchMock).toHaveBeenCalledWith(
            '/live/sessions/session-1/state',
            expect.anything(),
        );
        expect(wrapper.find('[data-test="live-podium"]').exists()).toBe(true);
        expect(
            wrapper
                .findAll('[data-test="live-result-score"]')
                .map((c) => c.text()),
        ).toEqual(['3.00', '2.00']);
        expect(
            wrapper
                .findAll('[data-test="live-question-row"]')
                .map((r) => r.text()),
        ).toEqual([
            expect.stringContaining('21'),
            expect.stringContaining('00'),
        ]);
    });

    it('shows neither a ranking nor a podium when leaderboards are disabled', () => {
        const finished = hostState({
            results: {
                leaderboard: false,
                ranking: null,
                podium: null,
                participants: [
                    {
                        id: 'a',
                        student: row('a').student,
                        score: '3.00',
                        final_rank: null,
                    },
                ],
                questions: [],
            },
        });
        finished.progress.session.status = 'finished';
        const wrapper = mountView(finished);

        expect(wrapper.find('[data-test="live-podium"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="live-ranking"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="live-result-rank"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="live-no-leaderboard"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="live-scores"]').text()).toContain(
            '3.00',
        );
    });

    it('polls every 2 seconds while the websocket is down', async () => {
        echo.connector.pusher.connection.state = 'unavailable';
        const wrapper = mountView(hostState());
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(wrapper.find('[data-test="live-reconnecting"]').exists()).toBe(
            true,
        );
        fetchMock.mockClear();
        await vi.advanceTimersByTimeAsync(4_000);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
});
