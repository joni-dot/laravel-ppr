import { asManager } from '../support/manager';
import { projects, riskCheckPackages } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.riskCheck;
const tank = riskCheckPackages.watchedTank;

test('a package owner is told when a project manager makes their package late', async ({
    page,
    browser,
    baseURL,
}) => {
    await asManager(browser, baseURL, async (managerPage) => {
        await managerPage.goto(`/projects/${id}/packages`);
        await managerPage.getByRole('link', { name: tank.title }).click();
        await managerPage.getByRole('link', { name: 'Edit dates' }).click();
        // A month before the forecast delivery, so the tank turns red.
        await managerPage
            .getByLabel('Planned date of Required on site')
            .fill('2098-12-01');
        await managerPage.getByRole('button', { name: 'Save dates' }).click();
        await expect(managerPage.getByText('Dates saved.')).toBeVisible();
    });

    // The check runs in the background after the save; here the queue runs it straight away.
    await page.goto('/notifications');
    const message = `${tank.number} ${tank.title}: risk is now Red (was No risk): Late: expected 31 days after the need date.`;
    const notification = page
        .getByTestId('unread-notification')
        .filter({ hasText: message });
    await expect(notification).toContainText(projects.riskCheck.name);

    await notification.getByRole('link', { name: message }).click();
    await expect(page.getByRole('heading', { name: tank.title })).toBeVisible();
    await expect(
        page.getByRole('region', { name: 'Risk' }).getByTestId('risk-badge'),
    ).toHaveText('Red');
});
