export type User = {
    id: number;
    public_id?: string;
    current_workspace_id?: number | null;
    name: string;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    email: string;
    avatar?: string;
    is_admin?: boolean;
    is_super_admin?: boolean;
    is_banned?: boolean;
    banned_at?: string | null;
    ban_reason?: string | null;
    profile_visibility?: 'section' | 'private';
    profile_show_activity?: boolean;
    profile_show_sections?: boolean;
    profile_show_social?: boolean;
    profile_show_achievements?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
