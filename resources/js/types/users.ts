import type { Role } from './auth';

export type UserStatus = 'active' | 'invited' | 'deactivated';

export type RoleOption = {
    value: Role;
    label: string;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    status: UserStatus;
    can: {
        update: boolean;
        deactivate: boolean;
        reactivate: boolean;
        resendInvitation: boolean;
    };
};
