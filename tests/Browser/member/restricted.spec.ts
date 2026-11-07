import { expect, test } from '../support/test';

test('a member can open the dashboard but sees no admin menu', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await expect(
        page.getByRole('heading', { name: 'Dashboard', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Administration')).toHaveCount(0);
});

for (const url of ['/admin/users', '/admin/settings']) {
    test(`${url} is forbidden for a member`, async ({ page }) => {
        const response = await page.request.get(url);

        expect(response.status()).toBe(403);
    });
}
