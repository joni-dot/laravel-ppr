import type { Page } from '@playwright/test';
import { finishedParentProject } from './test-data';
import { expect } from './test';

const { id, cancelledParent, subPackage } = finishedParentProject;

/**
 * "Leftover bolts" would be red on its own (an order date long past, no owner or buyer), but it sits below a
 * cancelled package, so it counts as finished: the list shows no risk and its page says why.
 */
export async function expectSubPackageOfCancelledPackageExplained(
    page: Page,
): Promise<void> {
    await page.goto(`/projects/${id}/packages`);

    const row = page.getByRole('row', { name: new RegExp(subPackage) });
    // Its own status stays as it is.
    await expect(row).toContainText('Ordered');
    await expect(row.getByRole('cell').nth(3)).toHaveText('');

    await page.getByRole('link', { name: subPackage }).click();
    const panel = page.getByRole('region', { name: 'Risk' });
    await expect(panel.getByTestId('risk-finished-note')).toHaveText(
        'A package above this one is closed or cancelled, so this one counts as finished: it is never at risk and gets no date reminders.',
    );
    await expect(panel.getByTestId('risk-badge')).toHaveCount(0);
    await expect(panel.getByRole('listitem')).toHaveCount(0);
    // Its missed order date isn't called overdue either.
    await expect(page.getByTestId('package-timeline')).toBeVisible();
    await expect(page.getByTestId('timeline-overdue')).toHaveCount(0);

    // The cancelled package itself keeps its own explanation.
    await page
        .getByRole('navigation', { name: 'Packages above this one' })
        .getByRole('link', { name: cancelledParent })
        .click();
    await expect(panel.getByTestId('risk-finished-note')).toHaveText(
        'Closed and cancelled packages are never at risk.',
    );
}
