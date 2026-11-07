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
