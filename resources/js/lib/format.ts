/**
 * Shows a date-only value such as "2026-10-29" in the user's own date format.
 * The parts are read one by one so the date never shifts because of time zones.
 */
export function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString();
}

/**
 * Shows a moment such as "2026-11-03T09:15:00+00:00" in the user's own time zone and format.
 */
export function formatDateTime(isoDateTime: string): string {
    return new Date(isoDateTime).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
