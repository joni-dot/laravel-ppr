import { expect, test } from '../support/test';

const pages = {
    '/admin/users': 'Users',
    '/admin/settings': 'Settings',
};

for (const [url, title] of Object.entries(pages)) {
    test(`${url} opens without browser errors`, async ({ page }) => {
        await page.goto(url);

        await expect(
            page.getByRole('heading', { name: title, exact: true }),
        ).toBeVisible();
    });
}

test('an admin can change the risk warning defaults', async ({ page }) => {
    await page.goto('/admin/settings');

    await page.getByLabel('Amber below').fill('21');
    await page.getByLabel('Red below').fill('3');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Settings saved.')).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Amber below')).toHaveValue('21');
    await expect(page.getByLabel('Red below')).toHaveValue('3');
});

test('an admin can invite a user', async ({ page }) => {
    await page.goto('/admin/users');

    await page.getByRole('button', { name: 'Invite user' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Browser Invitee');
    await dialog
        .getByLabel('Email address')
        .fill('invitee@browser-test.example');
    await dialog.getByRole('button', { name: 'Send invitation' }).click();

    await expect(dialog).toHaveCount(0);
    await expect(
        page.getByRole('cell', { name: 'invitee@browser-test.example' }),
    ).toBeVisible();
});
