import { projects, riskPackages } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.risk;
const { overduePump, calmValve } = riskPackages;

test('a viewer sees risk but cannot flag a package', async ({ page }) => {
    await page.goto(`/projects/${id}/packages`);
    await expect(
        page
            .getByRole('row', { name: new RegExp(overduePump.title) })
            .getByRole('cell')
            .nth(3),
    ).toContainText('Red');

    await page.goto(`/projects/${id}/packages/${overduePump.id}`);
    const panel = page.getByRole('region', { name: 'Risk' });
    await expect(panel.getByTestId('risk-badge')).toHaveText('Red');
    await expect(panel.getByLabel('Reason for the high-risk flag')).toHaveCount(
        0,
    );
    await expect(
        panel.getByRole('button', { name: 'Flag as high risk' }),
    ).toHaveCount(0);

    // The server refuses too, not only the page.
    const xsrf = (await page.context().cookies()).find(
        (cookie) => cookie.name === 'XSRF-TOKEN',
    );
    const response = await page.request.post(
        `/projects/${id}/packages/${calmValve.id}/risk-flag`,
        {
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(xsrf?.value ?? ''),
            },
            data: { reason: 'Sneaky' },
        },
    );
    expect(response.status()).toBe(403);
});
