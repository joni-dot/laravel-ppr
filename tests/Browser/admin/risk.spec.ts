import { shownDate } from '../support/dates';
import { projects, riskPackages } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.risk;
const { mainSkid, overduePump, unownedCrate, calmValve } = riskPackages;

test('the list shows each package’s risk and filters by it', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/packages`);

    const riskCell = (title: string) =>
        page
            .getByRole('row', { name: new RegExp(title) })
            .getByRole('cell')
            .nth(3);
    await expect(riskCell(overduePump.title)).toContainText('Red');
    await expect(riskCell(overduePump.title)).toContainText(
        'Overdue: Order placed',
    );
    await expect(riskCell(unownedCrate.title)).toContainText('Amber');
    await expect(riskCell(unownedCrate.title)).toContainText(
        'No package owner; No responsible buyer',
    );
    // A sub-package's overdue order doesn't make the package above it late.
    await expect(riskCell(mainSkid.title)).toHaveText('');
    await expect(riskCell(calmValve.title)).toHaveText('');

    const filter = page.getByRole('combobox', { name: 'Filter by risk' });
    await filter.selectOption({ label: 'Red only' });
    await expect(page.getByText('1 of 4 packages match')).toBeVisible();
    // The package above a match stays, so the match can be found in the tree.
    await expect(
        page.getByRole('link', { name: mainSkid.title }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: overduePump.title }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: unownedCrate.title }),
    ).toHaveCount(0);

    await filter.selectOption({ label: 'At risk (amber or red)' });
    await expect(page.getByText('2 of 4 packages match')).toBeVisible();
    await expect(
        page.getByRole('link', { name: unownedCrate.title }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: calmValve.title })).toHaveCount(
        0,
    );

    await page.getByRole('button', { name: 'Clear filters' }).click();
    await expect(
        page.getByRole('link', { name: calmValve.title }),
    ).toBeVisible();
});

test('the package page explains why a package is at risk', async ({ page }) => {
    await page.goto(`/projects/${id}/packages/${overduePump.id}`);

    const panel = page.getByRole('region', { name: 'Risk' });
    await expect(panel.getByTestId('risk-badge')).toHaveText('Red');
    await expect(panel).toContainText(
        `Overdue: Order placed was expected on ${await shownDate(page, overduePump.overdueOrder)}`,
    );
});

test('an admin flags a package as high risk, changes the reason and clears it', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/packages/${calmValve.id}`);

    const panel = page.getByRole('region', { name: 'Risk' });
    await expect(panel).toContainText(
        'Nothing puts this package at risk right now.',
    );
    await expect(panel.getByTestId('risk-badge')).toHaveCount(0);

    await panel
        .getByLabel('Reason for the high-risk flag')
        .fill('Supplier went bankrupt');
    await panel.getByRole('button', { name: 'Flag as high risk' }).click();

    await expect(page.getByText('Package flagged as high risk.')).toBeVisible();
    await expect(panel.getByTestId('risk-badge')).toHaveText('Red');
    await expect(panel).toContainText(
        'Flagged as high risk: Supplier went bankrupt',
    );
    await expect(panel.getByTestId('risk-flagged-by')).toContainText(
        'Flagged by Browser Admin on',
    );

    await panel
        .getByLabel('Reason for the high-risk flag')
        .fill('New supplier starts late');
    await panel.getByRole('button', { name: 'Save reason' }).click();
    await expect(panel).toContainText(
        'Flagged as high risk: New supplier starts late',
    );

    await page.goto(`/projects/${id}/packages`);
    await expect(
        page
            .getByRole('row', { name: new RegExp(calmValve.title) })
            .getByRole('cell')
            .nth(3),
    ).toContainText('Red');

    // Clear it again, so the other tests find the package as it was seeded.
    await page.getByRole('link', { name: calmValve.title }).click();
    await panel.getByRole('button', { name: 'Clear flag' }).click();
    await expect(page.getByText('Risk flag cleared.')).toBeVisible();
    await expect(panel).toContainText(
        'Nothing puts this package at risk right now.',
    );
    await expect(panel.getByLabel('Reason for the high-risk flag')).toHaveValue(
        '',
    );
});
