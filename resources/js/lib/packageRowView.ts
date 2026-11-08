import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { statusBadge, riskBadge } from '@/lib/packageBadges';
import type { BadgeLook } from '@/lib/packageBadges';
import { riskReasonInWords } from '@/lib/packageRisk';
import { floatInWords, floatShort } from '@/lib/scheduleFloat';
import type { PackageRow, PurchaseType } from '@/types';

/**
 * Everything one row of the package list shows, as ready-made text.
 */
export type PackageRowView = {
    showUrl: string;
    editUrl: string;
    status: BadgeLook;
    risk: (BadgeLook & { summary: string }) | null;
    nextMilestoneDate: string | null;
    needDate: string;
    expectedNeedDate: string;
    float: { short: string; words: string; isLate: boolean } | null;
    purchaseType: string;
    showSubPackagesLabel: string;
    hideSubPackagesLabel: string;
    addSubPackageLabel: string;
    editLabel: string;
};

/**
 * Gives each row's view, worked out the first time the row is drawn and then remembered. The list only
 * draws the rows on screen, so most rows of a big project never need one. A reloaded list brings new row
 * objects, so the remembered views of old rows are dropped with them.
 */
export function packageRowViews(
    projectId: number,
    purchaseTypeLabels: ReadonlyMap<PurchaseType, string>,
): (row: PackageRow) => PackageRowView {
    const views = new WeakMap<PackageRow, PackageRowView>();

    return (row) => {
        let view = views.get(row);

        if (view === undefined) {
            view = viewOf(row, projectId, purchaseTypeLabels);
            views.set(row, view);
        }

        return view;
    };
}

function viewOf(
    row: PackageRow,
    projectId: number,
    purchaseTypeLabels: ReadonlyMap<PurchaseType, string>,
): PackageRowView {
    const number = { number: row.number };

    return {
        showUrl: PackageController.show.url([projectId, row.id]),
        editUrl: PackageController.edit.url([projectId, row.id]),
        status: statusBadge(row.status),
        risk: row.risk && {
            ...riskBadge(row.risk.level),
            summary: riskSummary(row),
        },
        nextMilestoneDate: row.nextMilestoneDate
            ? formatDate(row.nextMilestoneDate)
            : null,
        needDate: dateOrDash(row.needDate),
        expectedNeedDate: dateOrDash(row.expectedNeedDate),
        float:
            row.floatDays === null
                ? null
                : {
                      short: floatShort(row.floatDays),
                      words: floatInWords(row.floatDays),
                      isLate: row.floatDays < 0,
                  },
        purchaseType: row.purchaseType
            ? (purchaseTypeLabels.get(row.purchaseType) ?? '')
            : '',
        showSubPackagesLabel: trans('Show sub-packages of :number', number),
        hideSubPackagesLabel: trans('Hide sub-packages of :number', number),
        addSubPackageLabel: trans('Add a sub-package under :number', number),
        editLabel: trans('Edit :number', number),
    };
}

function riskSummary(row: PackageRow): string {
    if (row.risk === null) {
        return '';
    }

    const { reasons, overdueMilestone, flagReason } = row.risk;

    return reasons
        .map((reason) =>
            riskReasonInWords(reason, {
                floatDays: row.floatDays,
                overdueMilestone,
                flagReason,
            }),
        )
        .join('; ');
}

function dateOrDash(date: string | null): string {
    return date === null ? '–' : formatDate(date);
}
