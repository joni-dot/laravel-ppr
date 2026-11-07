import type { UserStatus } from './users';

export type ProjectRole =
    | 'project_manager'
    | 'buyer'
    | 'package_owner'
    | 'viewer';

export type ProjectRoleOption = {
    value: ProjectRole;
    label: string;
    description: string;
};

/**
 * What every project page receives for its title, breadcrumbs and tabs.
 */
export type ProjectHeader = {
    id: number;
    name: string;
    code: string;
    can: {
        update: boolean;
        manageMembers: boolean;
    };
};

export type ProjectListItem = {
    id: number;
    name: string;
    code: string;
    membersCount: number;
    myRole: string | null;
};

export type PersonOption = {
    id: number;
    name: string;
    email: string;
};

export type ProjectMember = {
    id: number;
    name: string;
    email: string;
    status: UserStatus;
    role: ProjectRole;
};

export type ProjectCategory = {
    id: number;
    code: string;
    name: string;
};

export type NumberSeparator =
    | 'hyphen'
    | 'dot'
    | 'slash'
    | 'underscore'
    | 'none';

export type SeparatorOption = {
    value: NumberSeparator;
    label: string;
    character: string;
};

export type NumberingScheme = {
    prefix: string | null;
    includesProjectCode: boolean;
    includesCategory: boolean;
    separator: NumberSeparator;
    padding: number;
    start: number;
    subSeparator: NumberSeparator;
    subPadding: number;
};
