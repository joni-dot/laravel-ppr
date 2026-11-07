import { trans } from '@/lib/i18n';

/**
 * Float is the number of days between a package's expected need date and its need date:
 * above zero is time to spare, below zero is late. The server works it out; this only shows it.
 */
export function floatInWords(days: number): string {
    if (days === 0) {
        return trans('No days to spare');
    }

    if (days > 0) {
        return days === 1
            ? trans('1 day to spare')
            : trans(':count days to spare', { count: days });
    }

    return days === -1
        ? trans('1 day late')
        : trans(':count days late', { count: -days });
}

/**
 * The same, short enough for a table column, such as "12 d" or "−5 d".
 */
export function floatShort(days: number): string {
    return trans(':count d', {
        count: days < 0 ? `−${-days}` : days,
    });
}
