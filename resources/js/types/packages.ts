export type PackageStatus =
    | 'draft'
    | 'planned'
    | 'tendering'
    | 'ordered'
    | 'delivered'
    | 'closed'
    | 'cancelled';

export type PurchaseType = 'equipment' | 'material' | 'service' | 'subcontract';

export type Option<Value extends string> = {
    value: Value;
    label: string;
};

/**
 * One row of the package tree. Rows arrive in tree order: every package right after its parent.
 */
export type PackageRow = {
    id: number;
    parentId: number | null;
    depth: number;
    number: string;
    title: string;
    categoryId: number | null;
    categoryCode: string | null;
    status: PackageStatus;
    purchaseType: PurchaseType | null;
    ownerName: string | null;
    buyerName: string | null;
    canEdit: boolean;
    // The first milestone that hasn't happened yet, and when it is expected.
    nextMilestoneName: string | null;
    nextMilestoneDate: string | null;
    // The first date from today on of a milestone not done yet, for the "due soon" filter.
    dueDate: string | null;
    // The planned date of the project's need-date milestone.
    needDate: string | null;
    // The latest expected need date of this package and its sub-packages.
    expectedNeedDate: string | null;
    // Days from the expected need date to the need date: below zero is late.
    floatDays: number | null;
    // Nothing when the package isn't at risk.
    risk: PackageListRisk | null;
};

export type RiskLevel = 'none' | 'amber' | 'red';

// In the order pages list them, most serious kind first. See app/Risk/RiskReason.php.
export type RiskReason =
    | 'manual_flag'
    | 'overdue'
    | 'late'
    | 'no_need_date'
    | 'no_owner'
    | 'owner_deactivated'
    | 'no_buyer'
    | 'buyer_deactivated';

/**
 * The risk column of the package list. The server works it out; details come only where a reason needs them.
 */
export type PackageListRisk = {
    level: Exclude<RiskLevel, 'none'>;
    reasons: RiskReason[];
    overdueMilestone?: string;
    flagReason?: string;
};

/**
 * A package's risk on its own page.
 */
export type PackageRiskOverview = {
    level: RiskLevel;
    reasons: RiskReason[];
    // Counting sub-packages, as the package list shows it.
    floatDays: number | null;
    thresholds: { amberDays: number; redDays: number };
    overdueMilestone: { name: string; expected: string | null } | null;
    // Kept even when the package is closed, so it can still be cleared.
    flag: {
        reason: string;
        flaggedByName: string | null;
        flaggedAt: string | null;
    } | null;
};

export type PackageLink = {
    id: number;
    number: string;
    title: string;
};

export type SubPackageRow = PackageLink & {
    categoryCode: string | null;
    status: PackageStatus;
};

export type PackageDetails = {
    id: number;
    parentId: number | null;
    number: string;
    title: string;
    category: { id: number; code: string; name: string } | null;
    status: PackageStatus;
    statusLabel: string;
    purchaseType: string | null;
    ownerName: string | null;
    buyerName: string | null;
    quantity: string | null;
    unit: string | null;
    description: string | null;
    notes: string | null;
};

export type EditablePackage = {
    id: number;
    number: string;
    title: string;
    categoryId: number | null;
    status: PackageStatus;
    purchaseType: PurchaseType | null;
    ownerId: number | null;
    ownerName: string | null;
    buyerId: number | null;
    buyerName: string | null;
    quantity: string | null;
    unit: string | null;
    description: string | null;
    notes: string | null;
};
