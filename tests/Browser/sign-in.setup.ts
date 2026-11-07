import { password, users } from './support/test-data';
import { expect, test } from './support/test';

// Signs in once per role, so the other tests can start signed in. The addresses
// exist only in the test data, so this also fails if the app is on another database.
for (const [role, email] of Object.entries(users)) {
    test(`sign in as ${role}`, async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email address').fill(email);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByTestId('login-button').click();

        await expect(page).toHaveURL(/\/dashboard$/);

        await page
            .context()
            .storageState({ path: `tests/Browser/.auth/${role}.json` });
    });
}
