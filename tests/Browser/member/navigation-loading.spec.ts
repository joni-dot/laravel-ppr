import type { Page } from '@playwright/test';
import { projects } from '../support/test-data';
import { expect, test } from '../support/test';

/**
 * Holds Inertia's requests for the path until the returned function is called, so the page stays loading
 * for as long as the test needs. The first, full page load isn't an Inertia request and passes at once.
 */
async function holdPageRequests(page: Page, path: string): Promise<() => void> {
    let release!: () => void;
    const released = new Promise<void>((resolve) => (release = resolve));

    await page.route(
        (url) => url.pathname === path,
        async (route) => {
            if (route.request().headers()['x-inertia']) {
                await released;
            }

            await route.continue();
        },
    );

    return release;
}

const projectPath = `/projects/${projects.visible.id}`;

test('a slow page dims the old page, blocks it and shows the progress bar until the new page arrives', async ({
    page,
}) => {
    await page.goto('/projects');
    const release = await holdPageRequests(page, projectPath);
    const content = page.getByTestId('page-content');

    await page.getByRole('link', { name: projects.visible.name }).click();

    await expect(content).toHaveAttribute('aria-busy', 'true');
    await expect(content).toHaveJSProperty('inert', true);
    await expect(content).toHaveCSS('opacity', '0.6');
    await expect(
        page.getByRole('heading', { name: 'Projects', exact: true }),
    ).toBeVisible();
    const bar = page.locator('#nprogress .bar');
    await expect(bar).toBeVisible();
    await expect(bar).toHaveCSS('height', '3px');
    await expect(page.locator('#nprogress .spinner')).toHaveCount(0);

    release();

    await expect(
        page.getByRole('heading', { name: projects.visible.name, exact: true }),
    ).toBeVisible();
    await expect(content).not.toHaveAttribute('aria-busy');
    await expect(content).toHaveJSProperty('inert', false);
    await expect(content).toHaveCSS('opacity', '1');
    await expect(page.locator('#nprogress')).toBeHidden();
    // The new page's top element fades in.
    await expect(content.locator(':scope > *').first()).toHaveCSS(
        'animation-name',
        'page-fade-in',
    );
});

test('a fast page never marks the old page as busy', async ({ page }) => {
    await page.goto('/projects');
    await page.getByTestId('page-content').evaluate((content) => {
        const seen = window as unknown as { sawBusyPage: boolean };
        seen.sawBusyPage = false;
        // The attribute is missing at the start, so any change to it means it was set.
        new MutationObserver(() => (seen.sawBusyPage = true)).observe(content, {
            attributeFilter: ['aria-busy'],
        });
    });

    await page.getByRole('link', { name: projects.visible.name }).click();

    await expect(
        page.getByRole('heading', { name: projects.visible.name, exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => (window as unknown as { sawBusyPage: boolean }).sawBusyPage,
        ),
    ).toBe(false);
});

test('with reduced motion the old page still dims, but nothing fades', async ({
    page,
}) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/projects');
    const release = await holdPageRequests(page, projectPath);
    const content = page.getByTestId('page-content');

    await page.getByRole('link', { name: projects.visible.name }).click();

    await expect(content).toHaveAttribute('aria-busy', 'true');
    await expect(content).toHaveCSS('opacity', '0.6');
    await expect(content).toHaveCSS('transition-duration', '0s');

    release();

    await expect(
        page.getByRole('heading', { name: projects.visible.name, exact: true }),
    ).toBeVisible();
    await expect(content.locator(':scope > *').first()).toHaveCSS(
        'animation-name',
        'none',
    );
});

test('a settings page, which sits in a second layout, is dimmed only once', async ({
    page,
}) => {
    await page.goto('/settings/profile');
    const release = await holdPageRequests(page, '/settings/appearance');

    // Clicked without the pointer resting on the link first, so it isn't prefetched and really waits.
    await page
        .getByRole('navigation', { name: 'Settings' })
        .getByRole('link', { name: 'Appearance' })
        .dispatchEvent('click');

    await expect(page.locator('[aria-busy="true"]')).toHaveCount(1);
    await expect(page.locator('[inert]')).toHaveCount(1);
    await expect(
        page
            .getByTestId('page-content')
            .getByRole('heading', { name: 'Settings', exact: true }),
    ).toBeVisible();

    release();

    await expect(page.locator('[aria-busy]')).toHaveCount(0);
    await expect(page).toHaveURL('/settings/appearance');
});
