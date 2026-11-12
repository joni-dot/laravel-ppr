import { shownDate } from '../support/dates';
import { dashboardProject, projects } from '../support/test-data';
import { expect, test } from '../support/test';

// "YYYY-MM-DD" this many days from today in UTC, as the server counts days.
function daysFromToday(days: number): string {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

test('a member sees their next actions and their projects’ numbers', async ({
    page,
}) => {
    await page.goto('/dashboard');

    // Other tests may give the member packages elsewhere, so only this project's actions are compared.
    const actions = page
        .getByTestId('next-action')
        .filter({ hasText: dashboardProject.code });
    const [overdue, comingUp] = dashboardProject.memberActions;
    await expect(actions).toHaveCount(2);

    await expect(actions.nth(0)).toHaveAttribute('data-kind', 'overdue');
    await expect(actions.nth(0)).toContainText(overdue.title);
    await expect(actions.nth(0)).toContainText(overdue.milestone);
    await expect(actions.nth(0)).toContainText('You own it');
    await expect(actions.nth(0)).toContainText(
        await shownDate(page, daysFromToday(-overdue.daysAgo)),
    );
    await expect(actions.nth(0).getByTestId('action-when')).toHaveText(
        `${overdue.daysAgo} days overdue`,
    );

    await expect(actions.nth(1)).toHaveAttribute('data-kind', 'coming_up');
    await expect(actions.nth(1)).toContainText(comingUp.title);
    await expect(actions.nth(1)).toContainText(comingUp.milestone);
    await expect(actions.nth(1).getByTestId('action-when')).toHaveText(
        `In ${comingUp.daysLeft} days`,
    );
    // The notification test may have changed the member's period from 7 to 14 days.
    await expect(page.getByTestId('action-period')).toContainText(
        /dates up to \d+ days ahead/,
    );

    const project = page.locator(
        `[data-project-code="${dashboardProject.code}"]`,
    );
    const { tiles } = dashboardProject;
    await expect(project.getByTestId('tile-red')).toHaveText(
        new RegExp(`^\\s*${tiles.red}\\s*Red\\s*$`),
    );
    await expect(project.getByTestId('tile-amber')).toContainText(
        `${tiles.amber}`,
    );
    await expect(project.getByTestId('tile-overdue')).toContainText(
        `${tiles.overdue}`,
    );
    await expect(project.getByTestId('tile-due-soon')).toContainText(
        `${tiles.dueSoon}`,
    );
    await expect(project.getByTestId('tile-red')).toHaveAttribute(
        'href',
        `/projects/${dashboardProject.id}/packages?risk=red`,
    );
    await expect(project.getByTestId('tile-due-soon')).toHaveAttribute(
        'href',
        `/projects/${dashboardProject.id}/packages?due=soon`,
    );
    await expect(project.getByTestId('tile-missing-people')).toHaveText(
        `Missing owner or buyer: ${tiles.missingPeople}`,
    );

    await expect(page.getByText(projects.hidden.name)).toHaveCount(0);
});

test('an action opens its package', async ({ page }) => {
    await page.goto('/dashboard');

    const [overdue] = dashboardProject.memberActions;
    await page
        .getByTestId('next-action')
        .filter({ hasText: dashboardProject.code })
        .getByRole('link', { name: overdue.title })
        .click();

    await expect(
        page.getByRole('heading', { name: new RegExp(overdue.title) }),
    ).toBeVisible();
    await expect(page).toHaveURL(
        new RegExp(`/projects/${dashboardProject.id}/packages/\\d+$`),
    );
});
