/**
 * One milestone on the package page, with the dates the server worked out. Dates are "YYYY-MM-DD".
 */
export type ScheduleMilestone = {
    id: number;
    name: string;
    isNeedDate: boolean;
    planned: string | null;
    forecast: string | null;
    actual: string | null;
    // Calendar days from the previous milestone to this one.
    leadDays: number | null;
    expected: string | null;
    // The last day it can happen without making the package late.
    latest: string | null;
    isDone: boolean;
    // The latest expected date among this package and its sub-packages that aren't cancelled.
    expectedWithSubPackages: string | null;
};

export type PackageScheduleSummary = {
    milestones: ScheduleMilestone[];
    needDate: string | null;
    expectedNeedDate: string | null;
    // Days to spare before the need date; below zero means late.
    floatDays: number | null;
    // Null when the package has no sub-packages.
    withSubPackages: {
        expectedNeedDate: string | null;
        floatDays: number | null;
    } | null;
};

/**
 * One row of the form that edits a package's dates.
 */
export type EditableMilestone = {
    id: number;
    name: string;
    isNeedDate: boolean;
    plannedDate: string | null;
    forecastDate: string | null;
    actualDate: string | null;
    leadDays: number | null;
};
