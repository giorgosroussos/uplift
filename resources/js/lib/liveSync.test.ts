import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { useLiveSync } from '@/composables/useLiveSync';
import {
    createLiveSync,
    echoConnection,
    fetchLiveState,
    LIVE_POLL_INTERVAL_MS,
} from './liveSync';
import type { LiveConnection } from './liveSync';

function fakeConnection(connected: boolean): LiveConnection & {
    set(value: boolean): void;
    listeners: number;
} {
    let state = connected;
    const listeners = new Set<(connected: boolean) => void>();

    return {
        isConnected: () => state,
        onChange(listener) {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        set(value: boolean) {
            state = value;
            listeners.forEach((listener) => listener(value));
        },
        get listeners() {
            return listeners.size;
        },
    };
}

async function flush(): Promise<void> {
    await Promise.resolve();
    await Promise.resolve();
}

describe('live sync (ARCH-006, FLOW-002)', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('polls every 2 seconds', () => {
        expect(LIVE_POLL_INTERVAL_MS).toBe(2000);
    });

    it('takes one snapshot and no polls while the websocket is connected', async () => {
        const fetchSnapshot = vi.fn().mockResolvedValue({ n: 1 });
        const onSnapshot = vi.fn();
        const sync = createLiveSync({
            fetchSnapshot,
            onSnapshot,
            connection: fakeConnection(true),
        });

        sync.start();
        await flush();
        await vi.advanceTimersByTimeAsync(10_000);

        expect(sync.mode()).toBe('live');
        expect(fetchSnapshot).toHaveBeenCalledTimes(1);
        expect(onSnapshot).toHaveBeenCalledWith({ n: 1 });
    });

    it('falls back to polling every 2 seconds when the connection is lost and stops on reconnection', async () => {
        const fetchSnapshot = vi.fn().mockResolvedValue({});
        const modes: string[] = [];
        const connection = fakeConnection(true);
        const sync = createLiveSync({
            fetchSnapshot,
            onSnapshot: vi.fn(),
            connection,
            onModeChange: (mode) => modes.push(mode),
        });

        sync.start();
        await flush();
        expect(fetchSnapshot).toHaveBeenCalledTimes(1);

        connection.set(false);
        expect(sync.mode()).toBe('polling');

        await vi.advanceTimersByTimeAsync(1999);
        expect(fetchSnapshot).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1);
        expect(fetchSnapshot).toHaveBeenCalledTimes(2);
        await vi.advanceTimersByTimeAsync(4000);
        expect(fetchSnapshot).toHaveBeenCalledTimes(4);

        // Back online: one catch-up snapshot, then no more polling.
        connection.set(true);
        await flush();
        expect(fetchSnapshot).toHaveBeenCalledTimes(5);
        await vi.advanceTimersByTimeAsync(10_000);
        expect(fetchSnapshot).toHaveBeenCalledTimes(5);

        expect(modes).toEqual(['live', 'polling', 'live']);
    });

    it('polls from the start without any websocket', async () => {
        const fetchSnapshot = vi.fn().mockResolvedValue({});
        const sync = createLiveSync({
            fetchSnapshot,
            onSnapshot: vi.fn(),
            connection: null,
        });

        sync.start();
        await flush();
        await vi.advanceTimersByTimeAsync(6000);

        expect(sync.mode()).toBe('polling');
        expect(fetchSnapshot).toHaveBeenCalledTimes(4);
    });

    it('keeps the last state when a poll fails and retries on the next one', async () => {
        const fetchSnapshot = vi
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue({ ok: true });
        const onSnapshot = vi.fn();
        const sync = createLiveSync({
            fetchSnapshot,
            onSnapshot,
            connection: null,
        });

        sync.start();
        await flush();
        expect(onSnapshot).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(2000);
        expect(onSnapshot).toHaveBeenCalledWith({ ok: true });
    });

    it('never overlaps snapshot requests', async () => {
        let resolve: (value: object) => void = () => {};
        const fetchSnapshot = vi.fn(
            () =>
                new Promise<object>((r) => {
                    resolve = r;
                }),
        );
        const sync = createLiveSync({
            fetchSnapshot,
            onSnapshot: vi.fn(),
            connection: null,
        });

        sync.start();
        await vi.advanceTimersByTimeAsync(6000);
        expect(fetchSnapshot).toHaveBeenCalledTimes(1);

        resolve({});
        await flush();
        await vi.advanceTimersByTimeAsync(2000);
        expect(fetchSnapshot).toHaveBeenCalledTimes(2);
    });

    it('stops polling, listening and reporting when stopped', async () => {
        const fetchSnapshot = vi.fn().mockResolvedValue({});
        const onSnapshot = vi.fn();
        const connection = fakeConnection(false);
        const sync = createLiveSync({ fetchSnapshot, onSnapshot, connection });

        sync.start();
        expect(connection.listeners).toBe(1);
        sync.stop();
        await flush();
        await vi.advanceTimersByTimeAsync(10_000);

        expect(connection.listeners).toBe(0);
        expect(fetchSnapshot).toHaveBeenCalledTimes(1);
        expect(onSnapshot).not.toHaveBeenCalled();
    });

    it('reads the connection state of the Echo socket', () => {
        const handlers: Array<(s: { current: string }) => void> = [];
        const connection = {
            state: 'connected',
            bind: vi.fn((_: string, h: (s: { current: string }) => void) =>
                handlers.push(h),
            ),
            unbind: vi.fn(),
        };
        const echo = { connector: { pusher: { connection } } };
        const live = echoConnection(echo as never);
        const changes: boolean[] = [];

        expect(live.isConnected()).toBe(true);
        const off = live.onChange((connected) => changes.push(connected));
        handlers[0]({ current: 'unavailable' });
        handlers[0]({ current: 'connected' });
        off();

        expect(changes).toEqual([false, true]);
        expect(connection.bind).toHaveBeenCalledWith(
            'state_change',
            handlers[0],
        );
        expect(connection.unbind).toHaveBeenCalledWith(
            'state_change',
            handlers[0],
        );
    });

    it('fetches the snapshot as JSON and rejects on errors', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ results: null }), {
                    status: 200,
                }),
            )
            .mockResolvedValueOnce(new Response('{}', { status: 403 }));
        vi.stubGlobal('fetch', fetchMock);

        await expect(fetchLiveState('/live/sessions/1/state')).resolves.toEqual(
            { results: null },
        );
        await expect(fetchLiveState('/live/sessions/1/state')).rejects.toThrow(
            '403',
        );
        expect(fetchMock.mock.calls[0][1]).toMatchObject({
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
    });

    it('polls from a component without websocket and stops when it unmounts', async () => {
        const fetchMock = vi.fn(
            async () => new Response('{"n":1}', { status: 200 }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const snapshots: unknown[] = [];

        const wrapper = mount(
            defineComponent({
                setup() {
                    const { mode } = useLiveSync({
                        url: () => '/state',
                        onSnapshot: (s) => snapshots.push(s),
                    });

                    return () => h('span', mode.value);
                },
            }),
        );
        await flush();
        await nextTick();

        expect(wrapper.text()).toBe('polling');
        await vi.advanceTimersByTimeAsync(2000);
        expect(fetchMock).toHaveBeenCalledTimes(2);

        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(10_000);
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(snapshots[0]).toEqual({ n: 1 });
    });
});
