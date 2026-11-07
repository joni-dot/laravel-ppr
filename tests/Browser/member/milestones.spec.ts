import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.milestones;

test('a viewer does not see the milestone list', async ({ page }) => {
    await page.goto(`/projects/${id}`);

    await expect(
        page.getByRole('heading', {
            name: projects.milestones.name,
            exact: true,
        }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Milestones' })).toHaveCount(0);

    const response = await page.request.get(`/projects/${id}/milestones`);
    expect(response.status()).toBe(403);
});
