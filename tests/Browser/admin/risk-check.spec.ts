import { shownDate } from '../support/dates';
import { riskCheckPackages } from '../support/test-data';
import { expect, test } from '../support/test';

const compressor = riskCheckPackages.remindedCompressor;

/**
 * A date this many days from today (UTC, as the daily check counts) as "YYYY-MM-DD".
 */
function daysFromToday(days: number): string {
    return new Date(Date.now() + days * 86_400_000).toISOString().slice(0, 10);
}

test('the daily check tells a package owner about overdue and coming dates', async ({
    page,
}) => {
    const orderDate = await shownDate(
        page,
        daysFromToday(-compressor.orderDaysAgo),
    );
    const deliveryDate = await shownDate(
        page,
        daysFromToday(compressor.deliveryInDays),
    );
    const name = `${compressor.number} ${compressor.title}`;

    await page.goto('/notifications');
    const unread = page.getByTestId('unread-notification');

    await expect(
        unread.filter({
            hasText: `${name}: Delivered from supplier is expected in 3 days (${deliveryDate}).`,
        }),
    ).toHaveCount(1);
    await expect(
        unread.filter({
            hasText: `${name}: Order placed was expected on ${orderDate} and isn't marked done.`,
        }),
    ).toHaveCount(1);
    await expect(
        unread.filter({
            hasText: `${name}: risk is now Red (was No risk): Overdue: Order placed was expected on ${orderDate}.`,
        }),
    ).toHaveCount(1);
});
