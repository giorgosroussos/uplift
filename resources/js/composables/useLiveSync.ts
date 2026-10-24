import type Echo from 'laravel-echo';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';
import { createLiveSync, echoConnection, fetchLiveState } from '@/lib/liveSync';
import type { LiveSync, LiveSyncMode } from '@/lib/liveSync';

export interface UseLiveSyncOptions<T> {
    /** URL of the state snapshot (live.sessions.state / live.participants.state). */
    url: () => string;
    onSnapshot: (snapshot: T) => void;
    /** Subscribes to the live channels; returns how to leave them. */
    subscribe?: (echo: Echo<'reverb'>) => () => void;
}

/**
 * A live quiz view's real-time state (FLOW-002): subscribes to the
 * channels while mounted, takes a snapshot at mount and after each
 * reconnection, and polls every 2 seconds while the websocket is down
 * (mode "polling", e.g. for a reconnecting banner).
 */
export function useLiveSync<T>(options: UseLiveSyncOptions<T>): {
    mode: Ref<LiveSyncMode>;
    refresh: () => Promise<void>;
} {
    const mode = ref<LiveSyncMode>('polling');
    let sync: LiveSync | null = null;
    let leave: (() => void) | null = null;

    onMounted(() => {
        const echo = window.Echo;

        leave = echo && options.subscribe ? options.subscribe(echo) : null;
        sync = createLiveSync<T>({
            fetchSnapshot: () => fetchLiveState<T>(options.url()),
            onSnapshot: options.onSnapshot,
            connection: echo ? echoConnection(echo) : null,
            onModeChange: (next) => {
                mode.value = next;
            },
        });
        sync.start();
    });

    onBeforeUnmount(() => {
        sync?.stop();
        leave?.();
        sync = null;
        leave = null;
    });

    return {
        mode,
        refresh: () => sync?.refresh() ?? Promise.resolve(),
    };
}
