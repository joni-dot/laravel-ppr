import { projects, seededPackage } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.packages;

const pages = [
    `/projects/${id}/packages`,
    `/projects/${id}/packages/${seededPackage.id}`,
    `/projects/${id}/packages/${seededPackage.id}/edit`,
];

for (const url of pages) {
    test(`${url} opens without browser errors`, async ({ page }) => {
        await page.goto(url);

        await expect(
            page.getByRole('heading', {
                name: projects.packages.name,
                exact: true,
            }),
        ).toBeVisible();
    });
}

test('an admin adds packages and sub-packages at any depth', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/packages`);

    await page.getByLabel('Title of the new package').fill('Cable trays');
    await page
        .getByRole('combobox', { name: 'Category' })
        .first()
        .selectOption({ label: 'ELE – Electrical' });
    await page.getByRole('button', { name: 'Add package' }).click();
    await expect(page.getByText('Package PP3-ELE-0002 added.')).toBeVisible();

    // The form stays ready for the next package.
    await expect(page.getByLabel('Title of the new package')).toHaveValue('');
    await expect(page.getByLabel('Title of the new package')).toBeFocused();

    await page
        .getByRole('button', {
            name: 'Add a sub-package under PP3-ELE-0002',
            exact: true,
        })
        .click();
    const subTitle = page.getByLabel(
        'Title of a new sub-package under PP3-ELE-0002',
        { exact: true },
    );
    await expect(subTitle).toBeFocused();
    await subTitle.fill('Brackets');
    await subTitle.press('Enter');
    await expect(
        page.getByText('Package PP3-ELE-0002.01 added.'),
    ).toBeVisible();

    await page
        .getByRole('button', {
            name: 'Add a sub-package under PP3-ELE-0002.01',
        })
        .click();
    const deeperTitle = page.getByLabel(
        'Title of a new sub-package under PP3-ELE-0002.01',
    );
    await deeperTitle.fill('Bolts');
    await deeperTitle.press('Enter');
    await expect(
        page.getByText('Package PP3-ELE-0002.01.01 added.'),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Done' }).click();

    const bolts = page.getByRole('link', { name: 'Bolts' });
    await expect(bolts).toBeVisible();

    await page
        .getByRole('button', {
            name: 'Hide sub-packages of PP3-ELE-0002',
            exact: true,
        })
        .click();
    await expect(bolts).toHaveCount(0);
    await page
        .getByRole('button', {
            name: 'Show sub-packages of PP3-ELE-0002',
            exact: true,
        })
        .click();
    await expect(bolts).toBeVisible();

    await page.getByLabel('Search packages').fill('bolts');
    await expect(page.getByText('1 of 4 packages match')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Cable trays' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Seeded pump' })).toHaveCount(
        0,
    );
});

test('an admin edits a package and sees it on its page', async ({ page }) => {
    await page.goto(`/projects/${id}/packages`);
    await page.getByRole('link', { name: seededPackage.title }).click();

    await expect(
        page.getByRole('heading', { name: seededPackage.title }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Edit package' }).click();
    await expect(
        page.getByRole('heading', { name: `Edit ${seededPackage.number}` }),
    ).toBeVisible();

    await page.getByLabel('Title', { exact: true }).fill('Seeded cooling pump');
    await page.getByLabel('Status').selectOption({ label: 'Ordered' });
    await page.getByLabel('Purchase type').selectOption({ label: 'Equipment' });
    await page.getByLabel('Quantity (optional)').fill('2');
    await page.getByLabel('Unit').fill('pcs');
    await page.getByLabel('Package owner').selectOption({
        label: 'Browser Manager (manager@browser-test.example)',
    });
    await page.getByRole('button', { name: 'Save package' }).click();

    await expect(page.getByText('Package saved.')).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Seeded cooling pump' }),
    ).toBeVisible();
    await expect(page.getByText(seededPackage.number).first()).toBeVisible();
    await expect(page.getByText('Ordered')).toBeVisible();
    await expect(page.getByText('2 pcs')).toBeVisible();
    await expect(page.getByText('Browser Manager')).toBeVisible();

    // Put the title back, so the other tests find the package by its seeded name.
    await page.getByRole('link', { name: 'Edit package' }).click();
    await expect(
        page.getByRole('heading', { name: `Edit ${seededPackage.number}` }),
    ).toBeVisible();
    await page.getByLabel('Title', { exact: true }).fill(seededPackage.title);
    await page.getByRole('button', { name: 'Save package' }).click();
    await expect(page.getByText('Package saved.')).toBeVisible();
});
