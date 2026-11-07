import { shownDate } from '../support/dates';
import { projects, seededPackage } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.packages;

test('an admin plans a package and sees when it is expected', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/packages/${seededPackage.id}`);

    const milestones = page.getByRole('region', { name: 'Milestones' });
    await expect(
        milestones.getByRole('row', { name: /Installed \/ commissioned/ }),
    ).toContainText(await shownDate(page, seededPackage.installedForecast));

    await page.getByRole('link', { name: 'Edit dates' }).click();
    await expect(
        page.getByRole('heading', { name: `Dates of ${seededPackage.number}` }),
    ).toBeVisible();

    // The first milestone has nothing before it to count a lead time from.
    await expect(
        page.getByLabel('Lead time in days of Specification ready'),
    ).toHaveCount(0);

    await page
        .getByLabel('Planned date of Required on site')
        .fill('2027-06-30');
    await page.getByLabel('Forecast date of Order placed').fill('2027-01-10');
    await page
        .getByLabel('Lead time in days of Delivered from supplier')
        .fill('120');
    await page.getByLabel('Lead time in days of Required on site').fill('30');
    await page.getByRole('button', { name: 'Save dates' }).click();

    await expect(page.getByText('Dates saved.')).toBeVisible();
    await expect(
        page.getByRole('heading', { name: seededPackage.title }),
    ).toBeVisible();

    // Order placed 10 Jan + 120 days delivery + 30 days to site = 9 June, three weeks before 30 June.
    await expect(page.getByTestId('schedule-summary')).toHaveText(
        `Need date: ${await shownDate(page, '2027-06-30')} · Expected: ${await shownDate(page, '2027-06-09')} · 21 days to spare`,
    );
    const requiredOnSite = milestones.getByRole('row', {
        name: /Required on site/,
    });
    await expect(requiredOnSite).toContainText('Need date');
    await expect(requiredOnSite.getByRole('cell').nth(3)).toHaveText('30');
    await expect(requiredOnSite.getByRole('cell').nth(4)).toHaveText(
        await shownDate(page, '2027-06-09'),
    );

    // Clear the dates again, so the other tests find the package as it was seeded.
    await page.getByRole('link', { name: 'Edit dates' }).click();
    await page.getByLabel('Planned date of Required on site').fill('');
    await page.getByLabel('Forecast date of Order placed').fill('');
    await page
        .getByLabel('Lead time in days of Delivered from supplier')
        .fill('');
    await page.getByLabel('Lead time in days of Required on site').fill('');
    await page.getByRole('button', { name: 'Save dates' }).click();
    await expect(page.getByText('Dates saved.')).toBeVisible();
    await expect(page.getByTestId('schedule-summary')).toHaveText(
        'Need date: – · Expected: – · –',
    );
});

test('an actual date in the future is refused', async ({ page }) => {
    await page.goto(
        `/projects/${id}/packages/${seededPackage.id}/schedule/edit`,
    );

    const actual = page.getByLabel('Actual date of Specification ready');
    // The browser's own check would stop the form before the server sees it.
    await actual.evaluate((input) => input.removeAttribute('max'));
    await actual.fill('2099-01-01');
    await page.getByRole('button', { name: 'Save dates' }).click();

    await expect(
        page.getByText('An actual date can’t be in the future.'),
    ).toBeVisible();
});
