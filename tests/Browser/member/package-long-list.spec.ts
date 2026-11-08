import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

const { id, packageCount } = projects.longList;

test('a long package list draws only the rows near the screen, and every package can still be reached', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/packages`);

    const table = page.getByRole('table');
    const drawnRows = page.locator('tbody tr[aria-rowindex]');
    await expect(
        page.getByRole('link', { name: 'Long list item 001' }),
    ).toBeVisible();
    // Screen readers still hear how many rows there are: the packages and the header.
    await expect(table).toHaveAttribute(
        'aria-rowcount',
        String(packageCount + 1),
    );
    expect(await drawnRows.count()).toBeLessThan(100);
    await expect(
        page.getByRole('link', { name: 'Long list item 300' }),
    ).toHaveCount(0);

    await page.evaluate(() =>
        window.scrollTo(0, document.scrollingElement!.scrollHeight),
    );
    await expect(
        page.getByRole('link', { name: 'Long list item 300' }),
    ).toBeVisible();

    // The search box finds packages that aren't drawn.
    await page.getByLabel('Search packages').fill('item 150');
    await expect(
        page.getByText(`1 of ${packageCount} packages match`),
    ).toBeVisible();
    const match = page.getByRole('link', { name: 'Long list item 150' });
    await expect(match).toBeVisible();

    // The list's links are plain links, so check they still open inside the app without reloading the page.
    await page.evaluate(() => {
        (window as unknown as { stayedInApp: boolean }).stayedInApp = true;
    });
    await match.click();
    await expect(
        page.getByRole('heading', { name: 'Long list item 150' }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => (window as unknown as { stayedInApp?: boolean }).stayedInApp,
        ),
    ).toBe(true);
});
