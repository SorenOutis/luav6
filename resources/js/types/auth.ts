export type User = {
    id: number;
    public_id: string;
    name: string;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    email: string;
    avatar: string | null;
    cover_photo: string | null;
    bio: string | null;
    is_admin: boolean;
    is_super_admin: boolean;
    is_banned: boolean;
    banned_at: string | null;
    ban_reason: string | null;
    profile_visibility: 'section' | 'private';
    profile_show_activity: boolean;
    profile_show_sections: boolean;
    profile_show_social: boolean;
    profile_show_achievements: boolean;
    email_verified_at: string | null;
};

export type Auth = {
    // Authenticated application layouts always receive a user. Public pages
    // may receive null at runtime and only perform truthiness checks.
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
