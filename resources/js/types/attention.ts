import type { DashboardTiles } from './dashboard';
import type { PackageStatus } from './packages';

/**
 * A project's Overview tab numbers and charts, as app/Http/Presenters/ProjectAttentionOverview.php sends
 * them. ProjectAttention counts them all.
 */
export type ProjectAttentionOverview = {
    tiles: DashboardTiles;
    // Every status, zeros included, in the statuses' order.
    byStatus: StatusCount[];
    // Open packages only, by category id; packages without a category last.
    riskByCategory: CategoryRiskCount[];
    // This week and the 11 after it.
    milestonesDuePerWeek: WeekCount[];
    dueSoonDays: number;
};

export type StatusCount = {
    value: PackageStatus;
    label: string;
    count: number;
};

export type CategoryRiskCount = {
    // Null for packages without a category; its name is then "No category".
    categoryId: number | null;
    code: string | null;
    name: string;
    red: number;
    amber: number;
    none: number;
};

export type WeekCount = {
    // "YYYY-MM-DD", a Monday.
    weekStart: string;
    count: number;
};
