import { badgeVariants } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { trans } from '@/lib/i18n';
import { riskLevelInWords } from '@/lib/packageRisk';
import { cn } from '@/lib/utils';
import type { PackageStatus, RiskLevel } from '@/types';

/**
 * What a status or risk badge shows. Plain text and classes, so the package list can draw thousands of
 * badges without a component for each one, and still look the same as the badge components.
 */
export type BadgeLook = {
    label: string;
    className: string;
};

const statusVariants: Record<PackageStatus, BadgeVariants['variant']> = {
    draft: 'outline',
    planned: 'outline',
    tendering: 'secondary',
    ordered: 'default',
    delivered: 'default',
    closed: 'secondary',
    cancelled: 'destructive',
};

export function statusBadge(status: PackageStatus): BadgeLook {
    return {
        label: statusLabel(status),
        className: badgeVariants({ variant: statusVariants[status] }),
    };
}

const amberClasses =
    'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100';

// The level is written out too, so it doesn't depend on seeing colours.
export function riskBadge(level: Exclude<RiskLevel, 'none'>): BadgeLook {
    return {
        label: riskLevelInWords(level),
        className:
            level === 'red'
                ? badgeVariants({ variant: 'destructive' })
                : cn(badgeVariants({ variant: 'outline' }), amberClasses),
    };
}

function statusLabel(status: PackageStatus): string {
    switch (status) {
        case 'draft':
            return trans('Draft');
        case 'planned':
            return trans('Planned');
        case 'tendering':
            return trans('Tendering');
        case 'ordered':
            return trans('Ordered');
        case 'delivered':
            return trans('Delivered');
        case 'closed':
            return trans('Closed');
        case 'cancelled':
            return trans('Cancelled');
    }
}
