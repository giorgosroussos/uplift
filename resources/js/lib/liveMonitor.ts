/**
 * The host teacher's view of a live session (UC-024): progress rows kept
 * up to date from broadcasts and state snapshots, and the countdown.
 * Broadcasts and snapshots may arrive in any order; a participant's
 * progress only grows (answered questions, submission), so merging keeps
 * the furthest state of each participant and never goes back.
 */
import type {
    LiveHostState,
    LiveProgressRow,
    LiveSessionTiming,
} from '@/types/live';

function byJoin(a: LiveProgressRow, b: LiveProgressRow): number {
    if (a.joined_at !== b.joined_at) {
        return Date.parse(a.joined_at) - Date.parse(b.joined_at);
    }

    return a.id < b.id ? -1 : a.id > b.id ? 1 : 0;
}

function furthest(
    known: LiveProgressRow | undefined,
    next: LiveProgressRow,
): LiveProgressRow {
    if (!known) {
        return next;
    }

    return {
        ...next,
        answered: Math.max(known.answered, next.answered),
        submitted: known.submitted || next.submitted,
    };
}

/**
 * Adds or updates one participant (a '.live.progress.updated' broadcast:
 * join, answer, submit); the rows stay in join order.
 */
export function mergeProgressRow(
    rows: LiveProgressRow[],
    row: LiveProgressRow,
): LiveProgressRow[] {
    const index = rows.findIndex((known) => known.id === row.id);

    if (index === -1) {
        return [...rows, row].sort(byJoin);
    }

    const next = rows.slice();
    next[index] = furthest(rows[index], row);

    return next;
}

/**
 * The rows of a state snapshot, keeping anything newer a broadcast
 * already brought.
 */
export function mergeProgressRows(
    rows: LiveProgressRow[],
    snapshot: LiveProgressRow[],
): LiveProgressRow[] {
    const known = new Map(rows.map((row) => [row.id, row]));
    const merged = snapshot.map((row) => furthest(known.get(row.id), row));
    const seen = new Set(snapshot.map((row) => row.id));

    return [...merged, ...rows.filter((row) => !seen.has(row.id))].sort(byJoin);
}

/** Applies a state snapshot to the current state (null at first). */
export function applySnapshot(
    current: LiveHostState | null,
    snapshot: LiveHostState,
): LiveHostState {
    if (!current) {
        return snapshot;
    }

    return {
        ...snapshot,
        progress: {
            ...snapshot.progress,
            participants: mergeProgressRows(
                current.progress.participants,
                snapshot.progress.participants,
            ),
        },
    };
}

/** Applies a '.live.session.changed' broadcast (status and timing). */
export function applySessionChanged(
    current: LiveHostState,
    session: LiveSessionTiming,
): LiveHostState {
    if (session.id !== current.progress.session.id) {
        return current;
    }

    return {
        ...current,
        progress: {
            ...current.progress,
            session: { ...current.progress.session, ...session },
        },
    };
}

/** Applies a '.live.progress.updated' broadcast of this session. */
export function applyProgressUpdated(
    current: LiveHostState,
    sessionId: string,
    row: LiveProgressRow,
): LiveHostState {
    if (sessionId !== current.progress.session.id) {
        return current;
    }

    return {
        ...current,
        progress: {
            ...current.progress,
            participants: mergeProgressRow(current.progress.participants, row),
        },
    };
}

/**
 * The server clock's lead over this browser's clock in milliseconds, from
 * a server_now received at receivedAtMs (client time).
 */
export function clockOffsetMs(serverNow: string, receivedAtMs: number): number {
    return Date.parse(serverNow) - receivedAtMs;
}

/**
 * Whole seconds left until the common end time by the server's clock
 * (ARCH-006); null before the start, never negative.
 */
export function remainingSeconds(
    endsAt: string | null,
    offsetMs: number,
    nowMs: number,
): number | null {
    if (endsAt === null) {
        return null;
    }

    return Math.max(
        0,
        Math.ceil((Date.parse(endsAt) - (nowMs + offsetMs)) / 1000),
    );
}

/** Head counts of the progress view. */
export function progressSummary(rows: LiveProgressRow[]): {
    joined: number;
    submitted: number;
} {
    return {
        joined: rows.length,
        submitted: rows.filter((row) => row.submitted).length,
    };
}

/** A participant's share of answered questions, 0 to 100. */
export function answeredPercentage(
    answered: number,
    questionCount: number,
): number {
    if (questionCount <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((answered / questionCount) * 100));
}
