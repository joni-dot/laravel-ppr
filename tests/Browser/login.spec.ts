import { password, users } from './support/test-data';
import { expect, test } from './support/test';

test('signed-out visitors are sent to the login page', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/\/login$/);
});

test('a wrong password shows an error and does not sign in', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(users.member);
    await page.getByLabel('Password', { exact: true }).fill('not-the-password');
    await page.getByTestId('login-button').click();

    await expect(
        page.getByText('These credentials do not match'),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
});

test('a user can sign in and log out', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(users.member);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByTestId('login-button').click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.getByRole('button', { name: 'Browser Member' }).click();
    await page.getByTestId('logout-button').click();

    await expect(page).toHaveURL(/\/login$/);
});
