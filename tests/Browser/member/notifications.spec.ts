import type { Browser } from '@playwright/test';
import { asManager } from '../support/manager';
import {
    manager,
    notifiedPackage,
    password,
    projects,
    summaryPackages,
    users,
} from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.notifications;

/**
 * The manager has no saved sign-in, so they sign in in a browser window of their own.
 */
async function makeMemberTheBuyer(browser: Browser, baseURL?: string) {
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

    await page.goto(`/projects/${id}/packages`);
    await page.getByRole('link', { name: notifiedPackage.title }).click();
    await page.getByRole('link', { name: 'Edit package' }).click();
    await page.getByLabel('Responsible buyer').selectOption({
        label: `Browser Member (${users.member})`,
    });
    await page.getByRole('button', { name: 'Save package' }).click();
    await expect(page.getByText('Package saved.')).toBeVisible();

    await context.close();
}

test('a buyer is told a project manager made them buy a package', async ({
    page,
    browser,
    baseURL,
}) => {
    await makeMemberTheBuyer(browser, baseURL);

    await page.goto('/dashboard');
    const bell = page.getByTestId('notification-bell');
    await expect(bell).toHaveAccessibleName('Notifications, 1 unread');
    await expect(bell).toHaveText('1');

    await bell.click();
    await expect(
        page.getByRole('heading', { name: 'Notifications', exact: true }),
    ).toBeVisible();
    const message = `You are now the responsible buyer of ${notifiedPackage.number} ${notifiedPackage.title}.`;
    const unread = page.getByTestId('unread-notification');
    await expect(unread).toHaveCount(1);
    await expect(unread).toContainText(projects.notifications.name);

    await unread.getByRole('link', { name: message }).click();
    await expect(page).toHaveURL(new RegExp(`/projects/${id}/packages/\\d+$`));
    await expect(
        page.getByRole('heading', { name: notifiedPackage.title }),
    ).toBeVisible();
    await expect(bell).toHaveAccessibleName('Notifications');
    await expect(bell).toHaveText('');

    await bell.click();
    await expect(
        page.getByTestId('read-notification').getByRole('link', {
            name: message,
        }),
    ).toBeVisible();
    await expect(page.getByTestId('unread-notification')).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Mark all as read' }),
    ).toHaveCount(0);
});

test('a person chooses how many days ahead they are reminded', async ({
    page,
}) => {
    await page.goto('/settings/profile');
    await page
        .getByRole('navigation', { name: 'Settings' })
        .getByRole('link', { name: 'Notifications' })
        .click();

    const days = page.getByLabel('Remind me this many days before a date');
    await expect(days).toHaveValue('7');

    await days.fill('14');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Notification settings saved.')).toBeVisible();

    await page.reload();
    await expect(days).toHaveValue('14');
});

test('a package owner gets one summary when a change turns many of their packages amber', async ({
    page,
    browser,
    baseURL,
}) => {
    await asManager(browser, baseURL, async (managerPage) => {
        await managerPage.goto(`/projects/${id}/edit`);
        await managerPage
            .getByLabel('Amber below')
            .fill(String(summaryPackages.daysToSpare + 1));
        await managerPage.getByRole('button', { name: 'Save' }).click();
        await expect(managerPage.getByText('Project saved.')).toBeVisible();
    });

    // The check runs in the background after the save; here the queue runs it straight away.
    await page.goto('/notifications');
    const message = `${summaryPackages.count} packages in ${projects.notifications.name} changed risk level: 0 now red, ${summaryPackages.count} now amber, 0 no longer at risk.`;
    const summary = page
        .getByTestId('unread-notification')
        .filter({ hasText: message });
    await expect(summary).toHaveCount(1);
    await expect(
        page.getByTestId('unread-notification').filter({
            hasText: 'Summary pump',
        }),
    ).toHaveCount(0);

    await summary.getByRole('link', { name: message }).click();
    await expect(page).toHaveURL(new RegExp(`/projects/${id}/packages$`));
    await expect(
        page.getByRole('link', { name: 'Summary pump 11' }),
    ).toBeVisible();

    await page.goto('/notifications');
    await expect(
        page.getByTestId('read-notification').getByRole('link', {
            name: message,
        }),
    ).toBeVisible();
});
