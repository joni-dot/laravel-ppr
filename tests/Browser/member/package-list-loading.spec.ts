import type { Page } from '@playwright/test';
import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

const { id, name, packageCount } = projects.longList;

/**
 * Holds back the page's second request, the one for the package tree, until the test lets it through.
 */
async function holdBackThePackageTree(page: Page): Promise<() => void> {
    let letThrough = () => {};
    const released = new Promise<void>((resolve) => {
        letThrough = resolve;
    });

    await page.route(`**/projects/${id}/packages`, async (route) => {
        const asked = route.request().headers()['x-inertia-partial-data'];

        if (asked?.split(',').includes('packages')) {
            await released;
        }

        await route.continue();
    });

    return letThrough;
}

test('the package list opens at once and shows placeholder rows until the packages arrive', async ({
    page,
}) => {
    const letThrough = await holdBackThePackageTree(page);
    await page.goto(`/projects/${id}`);
    await page.getByRole('link', { name: 'Packages', exact: true }).click();

    // The top of the page is there while the packages are still on their way.
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    const placeholder = page.getByTestId('package-table-skeleton');
    await expect(placeholder).toBeVisible();
    await expect(placeholder).toHaveAttribute('aria-busy', 'true');
    await expect(
        placeholder.getByRole('columnheader', { name: 'Float', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: /Long list item/ }),
    ).toHaveCount(0);

    // Searching can start before the packages are there; the result shows once they arrive.
    await page.getByLabel('Search packages').fill('item 150');
    await expect(page.getByText(/packages match/)).toHaveCount(0);

    letThrough();

    await expect(
        page.getByRole('link', { name: 'Long list item 150' }),
    ).toBeVisible();
    await expect(
        page.getByText(`1 of ${packageCount} packages match`),
    ).toBeVisible();
    await expect(placeholder).toHaveCount(0);
    // The address shows the search only once the packages are there, so their answer can't undo it.
    await expect(page).toHaveURL(/\/packages\?search=item\+150$/);
});
