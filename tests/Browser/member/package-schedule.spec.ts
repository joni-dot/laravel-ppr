import { shownDate } from '../support/dates';
import { projects, seededPackage } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.packages;

test('a viewer sees the dates of a package but cannot edit them', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/packages/${seededPackage.id}`);

    const milestones = page.getByRole('region', { name: 'Milestones' });
    await expect(
        milestones.getByRole('row', { name: /Installed \/ commissioned/ }),
    ).toContainText(await shownDate(page, seededPackage.installedForecast));
    await expect(page.getByRole('link', { name: 'Edit dates' })).toHaveCount(0);

    const response = await page.request.get(
        `/projects/${id}/packages/${seededPackage.id}/schedule/edit`,
    );
    expect(response.status()).toBe(403);
});
