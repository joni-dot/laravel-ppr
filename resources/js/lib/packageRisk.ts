import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { floatInWords } from '@/lib/scheduleFloat';
import type { RiskLevel, RiskReason } from '@/types';

/**
 * What a reason needs to be put into words. Each field is there only when the reason needs it.
 */
export type RiskDetails = {
    floatDays?: number | null;
    overdueMilestone?: string;
    overdueDate?: string | null;
    flagReason?: string;
};

/**
 * One risk reason in plain words. The server decides the reasons; this only shows them.
 */
export function riskReasonInWords(
    reason: RiskReason,
    details: RiskDetails = {},
): string {
    switch (reason) {
        case 'manual_flag':
            return details.flagReason
                ? trans('Flagged as high risk: :reason', {
                      reason: details.flagReason,
                  })
                : trans('Flagged as high risk');
        case 'overdue':
            return overdueInWords(details);
        case 'late':
            return lateInWords(details.floatDays ?? null);
        case 'no_need_date':
            return trans('No need date planned, so its float is unknown');
        case 'no_owner':
            return trans('No package owner');
        case 'owner_deactivated':
            return trans('The package owner is deactivated');
        case 'no_buyer':
            return trans('No responsible buyer');
        case 'buyer_deactivated':
            return trans('The responsible buyer is deactivated');
    }
}

export function riskLevelInWords(level: RiskLevel): string {
    switch (level) {
        case 'red':
            return trans('Red');
        case 'amber':
            return trans('Amber');
        case 'none':
            return trans('No risk');
    }
}

function overdueInWords(details: RiskDetails): string {
    if (!details.overdueMilestone) {
        return trans('Overdue');
    }

    return details.overdueDate
        ? trans('Overdue: :milestone was expected on :date', {
              milestone: details.overdueMilestone,
              date: formatDate(details.overdueDate),
          })
        : trans('Overdue: :milestone', {
              milestone: details.overdueMilestone,
          });
}

function lateInWords(floatDays: number | null): string {
    if (floatDays === null || floatDays >= 0) {
        return trans('Short on time: :float', {
            float: floatDays === null ? '–' : floatInWords(floatDays),
        });
    }

    return floatDays === -1
        ? trans('Late: expected 1 day after the need date')
        : trans('Late: expected :count days after the need date', {
              count: -floatDays,
          });
}
