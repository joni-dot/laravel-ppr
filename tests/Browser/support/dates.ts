import type { Page } from '@playwright/test';

/**
 * A "YYYY-MM-DD" date as the app shows it, in the browser's own date format.
 */
export function shownDate(page: Page, isoDate: string): Promise<string> {
    return page.evaluate((date) => {
        const [year, month, day] = date.split('-').map(Number);

        return new Date(year, month - 1, day).toLocaleDateString();
    }, isoDate);
}
