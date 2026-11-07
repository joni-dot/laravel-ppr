import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

test('a member sees only their own projects and cannot create one', async ({
    page,
}) => {
    await page.goto('/projects');

    await expect(
        page.getByRole('link', { name: projects.visible.name }),
    ).toBeVisible();
    await expect(page.getByText(projects.hidden.name)).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'New project' })).toHaveCount(
        0,
    );
});

test('a viewer can look at the project but not change it', async ({ page }) => {
    await page.goto('/projects');
    await page.getByRole('link', { name: projects.visible.name }).click();

    await expect(
        page.getByRole('heading', { name: projects.visible.name, exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Numbering' })).toHaveCount(0);
    await expect(
        page.getByRole('link', { name: 'Project settings' }),
    ).toHaveCount(0);

    await page.getByRole('link', { name: 'Members', exact: true }).click();
    await expect(
        page.getByRole('cell', { name: 'Browser Manager' }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Add member' })).toHaveCount(
        0,
    );
});

for (const url of [
    `/projects/${projects.hidden.id}`,
    `/projects/${projects.visible.id}/edit`,
    `/projects/${projects.visible.id}/numbering`,
]) {
    test(`${url} is forbidden for this member`, async ({ page }) => {
        const response = await page.request.get(url);

        expect(response.status()).toBe(403);
    });
}
