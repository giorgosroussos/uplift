export type GradingScale = 'scale_0_10' | 'scale_0_100' | 'points_pass_mark';

/**
 * The shared prop `organization` (DATA-011); null outside an organisation.
 */
export type Organization = {
    name: string;
    logo_url: string | null;
    accent_color: string | null;
    student_term: string;
    teacher_term: string;
    leaderboard_enabled: boolean;
    grading_scale: GradingScale;
};
