import { users } from '../support/test-data';
import { expect, test } from '../support/test';

test('changing the email address asks for the current password', async ({
    page,
}) => {
    await page.goto('/settings/profile');

    const email = page.getByLabel('Email address');
    const currentPassword = page.getByLabel('Current password');

    await expect(currentPassword).toHaveCount(0);

    await email.fill('someone-else@browser-test.example');
    await expect(currentPassword).toBeVisible();

    await currentPassword.fill('not-the-password');
    await page.getByTestId('update-profile-button').click();

    await expect(page.getByText('The password is incorrect.')).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Email address')).toHaveValue(users.member);
});
