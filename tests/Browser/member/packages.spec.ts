import { projects, seededPackage } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.packages;

test('a viewer can look at packages but not add or edit them', async ({
    page,
}) => {
    await page.goto(`/projects/${id}`);
    await page.getByRole('link', { name: 'Packages', exact: true }).click();

    await expect(
        page.getByRole('link', { name: seededPackage.title }),
    ).toBeVisible();
    await expect(page.getByLabel('Title of the new package')).toHaveCount(0);
    await expect(
        page.getByRole('link', { name: `Edit ${seededPackage.number}` }),
    ).toHaveCount(0);

    await page.getByRole('link', { name: seededPackage.title }).click();
    await expect(
        page.getByRole('heading', { name: seededPackage.title }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit package' })).toHaveCount(
        0,
    );
});

for (const url of [
    `/projects/${id}/packages/${seededPackage.id}/edit`,
    `/projects/${projects.hidden.id}/packages`,
]) {
    test(`${url} is forbidden for this member`, async ({ page }) => {
        const response = await page.request.get(url);

        expect(response.status()).toBe(403);
    });
}
