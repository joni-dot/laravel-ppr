import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { riskLevelInWords, riskReasonInWords } from '@/lib/packageRisk';
import type { InAppNotification, NotificationKind } from '@/types';

type ParamsOf<Kind extends NotificationKind['kind']> = Extract<
    NotificationKind,
    { kind: Kind }
>['params'];

/**
 * The sentence shown for a notification, one case per kind. TypeScript refuses to build while a kind has no case.
 */
export function notificationMessage(notification: InAppNotification): string {
    const replacements = {
        package: `${notification.packageNumber} ${notification.packageTitle}`,
    };

    switch (notification.kind) {
        case 'package_assigned':
            return notification.params.role === 'owner'
                ? trans('You are now the owner of :package.', replacements)
                : trans(
                      'You are now the responsible buyer of :package.',
                      replacements,
                  );
        case 'date_coming_up':
            return dateComingUpMessage(
                notification.params,
                replacements.package,
            );
        case 'date_overdue':
            return trans(
                ":package: :milestone was expected on :date and isn't marked done.",
                {
                    ...replacements,
                    milestone: notification.params.milestone,
                    date: formatDate(notification.params.date),
                },
            );
        case 'risk_changed':
            return riskChangedMessage(
                notification.params,
                replacements.package,
            );
    }
}

function dateComingUpMessage(
    params: ParamsOf<'date_coming_up'>,
    packageName: string,
): string {
    const replacements = {
        package: packageName,
        milestone: params.milestone,
        date: formatDate(params.date),
        count: params.days_left,
    };

    switch (params.days_left) {
        case 0:
            return trans(
                ':package: :milestone is expected today (:date).',
                replacements,
            );
        case 1:
            return trans(
                ':package: :milestone is expected tomorrow (:date).',
                replacements,
            );
        default:
            return trans(
                ':package: :milestone is expected in :count days (:date).',
                replacements,
            );
    }
}

function riskChangedMessage(
    params: ParamsOf<'risk_changed'>,
    packageName: string,
): string {
    if (params.to === 'none') {
        return trans(':package is no longer at risk (it was :from).', {
            package: packageName,
            from: riskLevelInWords(params.from),
        });
    }

    const details = {
        floatDays: params.float_days,
        overdueMilestone: params.overdue_milestone ?? undefined,
        overdueDate: params.overdue_date,
    };

    return trans(':package: risk is now :to (was :from): :reasons.', {
        package: packageName,
        from: riskLevelInWords(params.from),
        to: riskLevelInWords(params.to),
        reasons: params.reasons
            .map((reason) => riskReasonInWords(reason, details))
            .join('; '),
    });
}
