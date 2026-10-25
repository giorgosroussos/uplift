import type { LiveStartMode } from '@/types/quiz';

/** ENT-LiveSession status (state model: lobby -> running | cancelled, running -> finished). */
export type LiveSessionStatus = 'lobby' | 'cancelled' | 'running' | 'finished';

/** Nickname and avatar only (SEC-008). */
export type LivePublicProfile = {
    id: string;
    nickname: string;
    avatar: string | null;
};

/** One participant's progress as the host sees it (OWN-012, OWN-013). */
export type LiveProgressRow = {
    id: string;
    student: LivePublicProfile;
    joined_at: string;
    answered: number;
    submitted: boolean;
};

/** The session's timing; countdowns derive from ends_at and server_now (ARCH-006). */
export type LiveSessionTiming = {
    id: string;
    status: LiveSessionStatus;
    started_at: string | null;
    ends_at: string | null;
    server_now: string;
};

export type LiveProgress = {
    session: LiveSessionTiming & { quiz_id: string; question_count: number };
    participants: LiveProgressRow[];
};

export type LiveRankingEntry = { rank: number; student: LivePublicProfile };

/** The host's results of a finished session (ShowLiveResults). */
export type LiveHostResults = {
    leaderboard: boolean;
    ranking: LiveRankingEntry[] | null;
    podium: LiveRankingEntry[] | null;
    participants: {
        id: string;
        student: LivePublicProfile;
        score: string | null;
        final_rank: number | null;
    }[];
    questions: {
        quiz_question_id: string;
        answered: number;
        correct: number;
    }[];
};

/** GET live/sessions/{id}/state */
export type LiveHostState = {
    channels: { session: string; host: string };
    progress: LiveProgress;
    results: LiveHostResults | null;
};

/** '.live.session.changed' on the private session channel. */
export type LiveSessionChangedPayload = { session: LiveSessionTiming };

/** '.live.progress.updated' on the host's presence channel. */
export type LiveProgressUpdatedPayload = {
    session_id: string;
    participant: LiveProgressRow;
};

export type LiveMonitorQuestion = {
    id: string;
    position: number;
    prompt: string;
    points: string;
};

/** Props of the teacher's monitor page (UC-024). */
export type LiveMonitorPage = {
    activity: {
        id: string;
        title: string;
        status: string;
        course_space_title: string;
    };
    quiz: {
        start_mode: LiveStartMode | null;
        scheduled_start_at: string | null;
        duration_seconds: number;
    };
    questions: LiveMonitorQuestion[];
    session: { id: string; status: LiveSessionStatus; hosting: boolean } | null;
    state: LiveHostState | null;
};
