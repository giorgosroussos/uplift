export type Role = 'student' | 'teacher' | 'org_admin';

export type User = {
    id: string;
    role: Role;
    name: string;
    nickname: string;
    email: string;
    avatar?: string | null;
    email_verified_at: string | null;
};

export type Auth = {
    user: User;
};
