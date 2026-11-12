import type { Page } from '@playwright/test';
import { dashboardProject } from '../support/test-data';
import { expect, test } from '../support/test';

/**
 * Every number on a project's Overview tab must open a package list showing exactly the packages it
 * counts. The numbers come from app/Dashboard/ProjectAttention.php and the list's filters from
 * resources/js/lib/packageFilters.ts; this test fails when the two disagree.
 */

const { id, tiles } = dashboardProject;
const overview = `/projects/${id}`;
const packageList = `/projects/${id}/packages`;

const tileNames = [
    'red',
    'amber',
    'overdue',
    'dueSoon',
    'missingPeople',
    'noNeedDate',
    'flagged',
] as const;

async function openOverview(page: Page): Promise<void> {
    await page.goto(overview);
    await expect(page.getByTestId('attention-tiles')).toBeVisible();
}

async function expectListToMatch(page: Page, count: number): Promise<void> {
    await expect(page).toHaveURL(/\/packages\?/);
    await expect(
        page.getByText(`${count} of ${tiles.total} packages match`, {
            exact: true,
        }),
    ).toBeVisible();
}

for (const name of tileNames) {
    test(`the ${name} number opens a list of exactly those packages`, async ({
        page,
    }) => {
        await openOverview(page);
        const tile = page.getByTestId(`tile-${name}`);
        const count = Number(
            await tile.getByTestId('tile-count').textContent(),
        );
        expect(count).toBe(tiles[name]);

        await tile.getByRole('link').click();

        await expectListToMatch(page, count);
    });
}

test('every status count opens a list of exactly those packages', async ({
    page,
}) => {
    await openOverview(page);
    const statuses = page.getByTestId('status-counts').getByRole('listitem');
    const testIds = await statuses.evaluateAll((items) =>
        items.map((item) => item.getAttribute('data-test') ?? ''),
    );
    let opened = 0;

    for (const testId of testIds) {
        await openOverview(page);
        const status = page.getByTestId(testId);
        const count = Number(
            await status.getByTestId('status-count').textContent(),
        );

        if (count === 0) {
            await expect(status.getByRole('link')).toHaveCount(0);
            continue;
        }

        await status.getByRole('link').click();
        await expect(page).toHaveURL(
            new RegExp(`\\?status=${testId.replace('status-', '')}$`),
        );
        await expectListToMatch(page, count);
        opened += count;
    }

    // Every package has one status, so the counts add up to all of them.
    expect(opened).toBe(tiles.total);
});

test('a category bar opens its red or amber packages', async ({ page }) => {
    await openOverview(page);
    const electrical = page
        .getByTestId('category-bar')
        .filter({ hasText: 'ELE – Electrical' });
    await expect(electrical.getByTestId('count-amber')).toHaveText('Amber: 2');
    await expect(electrical.getByTestId('count-red')).toHaveText('Red: 1');
    await expect(electrical.getByTestId('count-none')).toHaveText('No risk: 0');
    const categoryId = await electrical.getAttribute('data-category-id');

    await electrical.getByTestId('segment-amber').click();

    await expect(page).toHaveURL(
        new RegExp(`\\?category=${categoryId}&risk=amber$`),
    );
    await expectListToMatch(page, 2);
});

test('the overview shows the milestones due in the next 12 weeks', async ({
    page,
}) => {
    await openOverview(page);
    const bars = page.getByTestId('week-bar');
    await expect(bars).toHaveCount(12);
    // "Coming delivery" in 5 days, and the far-off dates of the others are outside the chart.
    const counts = (await bars.allTextContents()).map((text) =>
        Number(text.trim()),
    );
    expect(counts.reduce((sum, count) => sum + count, 0)).toBeGreaterThan(0);
    await expect(
        page.getByText(
            'Overdue milestones are counted in the Overdue number above.',
        ),
    ).toBeVisible();
});

test('the filters live in the address: opening, reloading, choosing and clearing them', async ({
    page,
}) => {
    await page.goto(`${packageList}?risk=red`);
    const risk = page.getByRole('combobox', { name: 'Filter by risk' });
    await expect(risk).toHaveValue('red');
    await expectListToMatch(page, tiles.red);

    await page.reload();
    await expect(risk).toHaveValue('red');
    await expectListToMatch(page, tiles.red);

    // Choosing a filter by hand changes the address without adding to the history.
    const historyLength = await page.evaluate(() => history.length);
    await page
        .getByRole('combobox', { name: 'Filter by what needs attention' })
        .selectOption({ label: 'Due within 14 days' });
    await expect(page).toHaveURL(/\?risk=red&due=soon$/);
    await expect(
        page.getByText(`0 of ${tiles.total} packages match`, { exact: true }),
    ).toBeVisible();
    await risk.selectOption({ label: 'All risk levels' });
    await expect(page).toHaveURL(/\?due=soon$/);
    await expectListToMatch(page, tiles.dueSoon);
    await page.getByLabel('Search packages').pressSequentially('delivery');
    await expect(page).toHaveURL(/\?search=delivery&due=soon$/);
    await expectListToMatch(page, 1);
    expect(await page.evaluate(() => history.length)).toBe(historyLength);

    await page.getByRole('button', { name: 'Clear filters' }).click();
    await expect(page).toHaveURL(new RegExp(`${packageList}$`));
    await expect(page.getByText(/packages match/)).toHaveCount(0);
    await expect(
        page.getByRole('link', { name: 'Overdue order' }),
    ).toBeVisible();
});

test('Back from a filtered list returns to the overview', async ({ page }) => {
    await openOverview(page);
    await page.getByTestId('tile-red').getByRole('link').click();
    await expectListToMatch(page, tiles.red);
    await page.getByRole('combobox', { name: 'Filter by risk' }).selectOption({
        label: 'At risk (amber or red)',
    });
    await expectListToMatch(page, tiles.red + tiles.amber);

    await page.goBack();

    await expect(page).toHaveURL(new RegExp(`${overview}$`));
    await expect(page.getByTestId('attention-tiles')).toBeVisible();

    // Forward again opens the list as it was left.
    await page.goForward();
    await expect(page).toHaveURL(/\?risk=atRisk$/);
    await expectListToMatch(page, tiles.red + tiles.amber);
});

test('due soon counts from the server day, whatever the browser clock says', async ({
    page,
}) => {
    // "Coming delivery" is due in 5 days; counted from this clock it would already be past.
    await page.clock.setFixedTime(
        new Date(Date.now() + 6 * 24 * 60 * 60 * 1000),
    );

    await page.goto(`${packageList}?due=soon`);

    await expectListToMatch(page, tiles.dueSoon);
});
