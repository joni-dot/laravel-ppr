import type { Browser, Page } from '@playwright/test';
import { manager, password } from './test-data';
import { expect } from './test';

/**
 * Does something as the project manager, who has no saved sign-in, in a browser window of their own,
 * so the test's own page stays signed in as the member or admin.
 */
export async function asManager(
    browser: Browser,
    baseURL: string | undefined,
    work: (page: Page) => Promise<void>,
): Promise<void> {
    const context = await browser.newContext({
        baseURL,
        storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();

    await page.goto('/login');
    await page.getByLabel('Email address').fill(manager);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByTestId('login-button').click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await work(page);
    await context.close();
}
