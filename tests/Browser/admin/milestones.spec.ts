import type { Page } from '@playwright/test';
import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

const { id } = projects.milestones;

function names(page: Page) {
    return page.getByTestId('milestone-name');
}

function row(page: Page, name: string) {
    return page
        .getByRole('row')
        .filter({ has: page.getByText(name, { exact: true }) });
}

test('an admin edits the milestone list of a project', async ({ page }) => {
    await page.goto(`/projects/${id}`);
    await page.getByRole('link', { name: 'Milestones' }).click();

    await expect(
        page.getByRole('heading', {
            name: projects.milestones.name,
            exact: true,
        }),
    ).toBeVisible();
    await expect(names(page)).toHaveText([
        'Specification ready',
        'Request for quotation sent',
        'Offers received',
        'Order placed',
        'Delivered from supplier',
        'Required on site',
        'Installed / commissioned',
    ]);
    await expect(row(page, 'Required on site')).toContainText('Need date');
    await expect(
        page.getByRole('button', { name: 'Move Specification ready up' }),
    ).toBeDisabled();
    await expect(
        page.getByRole('button', {
            name: 'Move Installed / commissioned down',
        }),
    ).toBeDisabled();

    // Add: new milestones go to the end, and the form is ready for the next one.
    const newName = page.getByLabel('Name of the new milestone');
    await newName.fill('Factory acceptance test');
    await page.getByRole('button', { name: 'Add milestone' }).click();
    await expect(page.getByText('Milestone added.')).toBeVisible();
    await expect(names(page).last()).toHaveText('Factory acceptance test');
    await expect(newName).toHaveValue('');

    // A name already in the list, whatever its case, is refused.
    await newName.fill('order PLACED');
    await page.getByRole('button', { name: 'Add milestone' }).click();
    await expect(
        page.getByText('The name has already been taken.'),
    ).toBeVisible();
    await newName.fill('');

    // Rename
    await page
        .getByRole('button', { name: 'Rename Factory acceptance test' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Site acceptance test');
    await dialog.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Milestone saved.')).toBeVisible();
    await expect(names(page).last()).toHaveText('Site acceptance test');

    // Move up, past the last default milestone.
    await page
        .getByRole('button', { name: 'Move Site acceptance test up' })
        .click();
    await expect(names(page).nth(6)).toHaveText('Site acceptance test');
    await expect(names(page).last()).toHaveText('Installed / commissioned');
    await expect(
        page.getByRole('button', {
            name: 'Move Installed / commissioned down',
        }),
    ).toBeDisabled();

    // Move the need date to another milestone.
    await expect(
        page.getByRole('button', { name: 'Remove Required on site' }),
    ).toBeDisabled();
    await page
        .getByRole('button', {
            name: 'Make Delivered from supplier the need date',
        })
        .click();
    await expect(
        page.getByText('Delivered from supplier is now the need date.'),
    ).toBeVisible();
    await expect(row(page, 'Delivered from supplier')).toContainText(
        'Need date',
    );
    await expect(row(page, 'Required on site')).not.toContainText('Need date');
    await expect(
        page.getByRole('button', { name: 'Remove Delivered from supplier' }),
    ).toBeDisabled();

    // Remove, after confirming.
    await page.getByRole('button', { name: 'Remove Required on site' }).click();
    const confirmation = page.getByRole('dialog');
    await expect(
        confirmation.getByText('Remove the milestone Required on site?'),
    ).toBeVisible();
    await expect(
        confirmation.getByText(
            'No package has dates or a lead time for this milestone yet.',
        ),
    ).toBeVisible();
    await confirmation
        .getByRole('button', { name: 'Remove', exact: true })
        .click();
    await expect(page.getByText('Milestone removed.')).toBeVisible();
    await expect(names(page)).toHaveCount(7);
    await expect(
        page.getByText('Required on site', { exact: true }),
    ).toHaveCount(0);
});
