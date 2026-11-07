export type Role = 'admin' | 'member';

export type User = {
    id: number;
    name: string;
    email: string;
    role: Role;
    avatar?: string;
};

export type Auth = {
    user: User;
    can: {
        manageUsers: boolean;
        manageSettings: boolean;
    };
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
