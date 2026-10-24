import type Echo from 'laravel-echo';

/**
 * ARCH-006: while the websocket connection is lost, the live quiz state is
 * polled over HTTP every 2 seconds.
 */
export const LIVE_POLL_INTERVAL_MS = 2000;

/** Broadcast names (Echo listens to them with a leading dot). */
export const LIVE_EVENTS = {
    sessionChanged: '.live.session.changed',
    progressUpdated: '.live.progress.updated',
} as const;

export type LiveSyncMode = 'live' | 'polling';

/** Whether the websocket is connected, and its changes. */
export interface LiveConnection {
    isConnected(): boolean;
    onChange(listener: (connected: boolean) => void): () => void;
}

export interface LiveSyncOptions<T> {
    /** Fetches the state snapshot (GET .../state). */
    fetchSnapshot: () => Promise<T>;
    onSnapshot: (snapshot: T) => void;
    /** The websocket connection; null when there is none (always poll). */
    connection: LiveConnection | null;
    onModeChange?: (mode: LiveSyncMode) => void;
    intervalMs?: number;
}

export interface LiveSync {
    start(): void;
    stop(): void;
    refresh(): Promise<void>;
    mode(): LiveSyncMode;
}

/**
 * Keeps a live quiz view in sync (FLOW-002): one snapshot at start; while
 * the websocket is connected, broadcasts do the rest; while it is lost, a
 * snapshot every 2 seconds; on reconnection, polling stops and one
 * snapshot catches up on anything missed. A failed fetch keeps the last
 * state and is retried by the next poll. Answers are never sent here:
 * they go over their own HTTP endpoint (ARCH-006).
 */
export function createLiveSync<T>(options: LiveSyncOptions<T>): LiveSync {
    const interval = options.intervalMs ?? LIVE_POLL_INTERVAL_MS;
    let timer: ReturnType<typeof setInterval> | null = null;
    let running = false;
    let inFlight = false;
    let current: LiveSyncMode = 'polling';
    let unsubscribe: (() => void) | null = null;

    async function refresh(): Promise<void> {
        if (!running || inFlight) {
            return;
        }

        inFlight = true;

        try {
            const snapshot = await options.fetchSnapshot();

            if (running) {
                options.onSnapshot(snapshot);
            }
        } catch {
            // Keep the last state; the next poll or reconnection retries.
        } finally {
            inFlight = false;
        }
    }

    function stopPolling(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    function setMode(mode: LiveSyncMode, notify: boolean): void {
        if (mode === 'polling' && timer === null) {
            timer = setInterval(() => void refresh(), interval);
        }

        if (mode === 'live') {
            stopPolling();
        }

        if (notify || mode !== current) {
            current = mode;
            options.onModeChange?.(mode);
        }
    }

    return {
        start(): void {
            if (running) {
                return;
            }

            running = true;
            setMode(
                options.connection?.isConnected() ? 'live' : 'polling',
                true,
            );
            unsubscribe =
                options.connection?.onChange((connected) => {
                    if (!running) {
                        return;
                    }

                    if (connected) {
                        setMode('live', false);
                        void refresh();
                    } else {
                        setMode('polling', false);
                    }
                }) ?? null;
            void refresh();
        },
        stop(): void {
            running = false;
            stopPolling();
            unsubscribe?.();
            unsubscribe = null;
        },
        refresh,
        mode: () => current,
    };
}

/** The connection state of Echo's Reverb (Pusher protocol) socket. */
export function echoConnection(echo: Echo<'reverb'>): LiveConnection {
    const connection = echo.connector.pusher.connection;

    return {
        isConnected: () => connection.state === 'connected',
        onChange(listener) {
            const handler = (states: { current: string }): void =>
                listener(states.current === 'connected');

            connection.bind('state_change', handler);

            return () => {
                connection.unbind('state_change', handler);
            };
        },
    };
}

/** GETs a live state snapshot; rejects on any non-2xx response. */
export async function fetchLiveState<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Live state request failed: ${response.status}`);
    }

    return (await response.json()) as T;
}
