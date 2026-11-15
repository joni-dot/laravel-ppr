import { shownDate } from '../support/dates';
import { projects, riskPackages } from '../support/test-data';
import { expect, test } from '../support/test';

const { overduePump } = riskPackages;

test('a package page shows its milestone dates on a timeline that screen readers can read', async ({
    page,
}) => {
    await page.goto(`/projects/${projects.risk.id}/packages/${overduePump.id}`);

    const milestones = page.getByRole('region', { name: 'Milestones' });
    const timeline = milestones.getByRole('img', {
        name: /^Timeline of the milestone dates/,
    });
    await expect(timeline).toBeVisible();

    const [order, needPlanned, needExpected] = await Promise.all(
        ['2020-01-15', '2099-06-01', '2099-01-01'].map((date) =>
            shownDate(page, date),
        ),
    );
    const description = (await timeline.getAttribute('aria-label')) ?? '';
    // The server's today, as the timeline says it, so a run across midnight can't disagree.
    const today = /Today is (.+)\.$/.exec(description)?.[1];
    expect(today).toBeTruthy();
    // The missed order date is named, and the order is now expected no earlier than today.
    expect(description).toContain(
        `Order placed: planned ${order}, was due ${order} and is overdue, expected ${today}`,
    );
    expect(description).toContain(
        `Required on site (need date): planned ${needPlanned}, expected ${needExpected}`,
    );

    // Only the two milestones with dates are drawn. The order slipped from its planned date to today and is
    // marked overdue; the need date's forecast is earlier than planned, so it didn't slip.
    await expect(timeline.getByTestId('timeline-milestone')).toHaveCount(2);
    await expect(timeline.getByTestId('timeline-slip')).toHaveCount(1);
    await expect(timeline.getByTestId('timeline-overdue')).toHaveCount(1);
    await expect(timeline.getByTestId('timeline-today')).toHaveCount(1);
    await expect(timeline.getByTestId('timeline-need-date')).toHaveCount(1);
});

test('a package without dates shows no timeline', async ({ page }) => {
    await page.goto(`/projects/${projects.longList.id}/packages`);
    await page.getByRole('link', { name: 'Long list item 001' }).click();

    const milestones = page.getByRole('region', { name: 'Milestones' });
    await expect(
        milestones.getByRole('row', { name: /Required on site/ }),
    ).toBeVisible();
    await expect(page.getByTestId('package-timeline')).toHaveCount(0);
    await expect(
        page.getByRole('img', { name: /^Timeline of the milestone dates/ }),
    ).toHaveCount(0);
});
