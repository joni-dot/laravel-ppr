import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.visible;

const pages = {
    '/projects': 'Projects',
    [`/projects/${id}`]: projects.visible.name,
    [`/projects/${id}/members`]: projects.visible.name,
    [`/projects/${id}/numbering`]: projects.visible.name,
    [`/projects/${id}/edit`]: projects.visible.name,
};

for (const [url, title] of Object.entries(pages)) {
    test(`${url} opens without browser errors`, async ({ page }) => {
        await page.goto(url);

        await expect(
            page.getByRole('heading', { name: title, exact: true }),
        ).toBeVisible();
    });
}

test('an admin creates a project and lands on its overview', async ({
    page,
}) => {
    await page.goto('/projects');

    await page.getByRole('button', { name: 'New project' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Browser Crane');
    await dialog.getByLabel('Project code').fill('BC3');
    await dialog
        .getByLabel('Project manager')
        .selectOption({ label: 'Browser Admin (admin@browser-test.example)' });
    await dialog.getByRole('button', { name: 'Create project' }).click();

    await expect(page.getByText('Project created.')).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Browser Crane', exact: true }),
    ).toBeVisible();
    await expect(page.getByTestId('example-package-number')).toHaveText(
        'BC3-ABC-0001',
    );
});

test('the numbering preview matches the numbers the server makes', async ({
    page,
}) => {
    await page.goto(`/projects/${id}/numbering`);

    await page.getByRole('button', { name: 'Add category' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Code').fill('ELE');
    await dialog.getByLabel('Name').fill('Electrical');
    await dialog.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Category added.')).toBeVisible();

    await page.getByLabel('Prefix (optional)').fill('PKG');
    await page.getByLabel('Separator', { exact: true }).selectOption('slash');
    await page.getByLabel('Digits', { exact: true }).fill('3');
    await page.getByLabel('First number').fill('42');
    await page.getByLabel('Sub-package separator').selectOption('hyphen');
    await page.getByLabel('Sub-package digits').fill('3');

    const preview = page.getByTestId('preview-package-number');
    const subPreview = page.getByTestId('preview-sub-package-number');
    await expect(preview).toHaveText('PKG/BP1/ELE/042');
    await expect(subPreview).toHaveText('PKG/BP1/ELE/042-001');

    await page.getByRole('button', { name: 'Save numbering' }).click();
    await expect(page.getByText('Numbering saved.')).toBeVisible();

    await page.getByRole('link', { name: 'Overview' }).click();
    await expect(page.getByTestId('example-package-number')).toHaveText(
        'PKG/BP1/ELE/042',
    );
    await expect(page.getByTestId('example-sub-package-number')).toHaveText(
        'PKG/BP1/ELE/042-001',
    );
});

test('an admin adds a member and changes their role', async ({ page }) => {
    await page.goto(`/projects/${id}/members`);

    await page.getByRole('button', { name: 'Add member' }).click();
    const dialog = page.getByRole('dialog');
    await dialog
        .getByLabel('Person')
        .selectOption({ label: 'Browser Admin (admin@browser-test.example)' });
    await dialog.getByLabel('Role in this project').selectOption('buyer');
    await dialog.getByRole('button', { name: 'Add member' }).click();
    await expect(page.getByText('Member added.')).toBeVisible();

    const role = page.getByLabel('Role of Browser Admin');
    await expect(role).toHaveValue('buyer');
    await role.selectOption('package_owner');
    await expect(page.getByText('Role changed.')).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Role of Browser Admin')).toHaveValue(
        'package_owner',
    );
});

test('the only project manager cannot be demoted', async ({ page }) => {
    await page.goto(`/projects/${id}/members`);

    await page.getByLabel('Role of Browser Manager').selectOption('viewer');

    await expect(
        page.getByText('A project needs at least one project manager.'),
    ).toBeVisible();
    await expect(page.getByLabel('Role of Browser Manager')).toHaveValue(
        'project_manager',
    );
});
