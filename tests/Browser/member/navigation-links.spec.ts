import type { Page, Request } from '@playwright/test';
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

/** Collects the Inertia requests for the path from now on. */
function recordPageRequests(page: Page, path: string): Request[] {
    const requests: Request[] = [];

    page.on('request', (request) => {
        if (
            new URL(request.url()).pathname === path &&
            request.headers()['x-inertia']
        ) {
            requests.push(request);
        }
    });

    return requests;
}

function isPrefetch(request: Request): boolean {
    return request.headers()['purpose'] === 'prefetch';
}

const sidebarLink = (page: Page, name: string) =>
    page.locator('[data-sidebar="content"]').getByRole('link', { name });

const projectTab = (page: Page, name: string) =>
    page
        .getByRole('navigation', { name: 'Project' })
        .getByRole('link', { name });

const projectPath = `/projects/${projects.visible.id}`;

test('a clicked sidebar link is highlighted at once and shows a spinner while its page is slow', async ({
    page,
}) => {
    await page.goto('/dashboard');
    const release = await holdPageRequests(page, '/projects');
    const dashboard = sidebarLink(page, 'Dashboard');
    const projectsLink = sidebarLink(page, 'Projects');
    await expect(dashboard).toHaveAttribute('aria-current', 'page');

    await projectsLink.click();

    // The dashboard is still on screen, but the highlight has already moved.
    await expect(
        page.getByRole('heading', { name: 'Dashboard', exact: true }),
    ).toBeVisible();
    await expect(projectsLink).toHaveAttribute('aria-current', 'page');
    await expect(projectsLink).toHaveAttribute('data-active', 'true');
    await expect(dashboard).not.toHaveAttribute('aria-current');
    await expect(dashboard).toHaveAttribute('data-active', 'false');
    await expect(
        projectsLink.getByRole('status', { name: 'Loading' }),
    ).toBeVisible();
    await expect(dashboard.getByRole('status')).toHaveCount(0);

    release();

    await expect(
        page.getByRole('heading', { name: 'Projects', exact: true }),
    ).toBeVisible();
    await expect(projectsLink).toHaveAttribute('aria-current', 'page');
    await expect(dashboard).not.toHaveAttribute('aria-current');
    await expect(page.getByRole('status', { name: 'Loading' })).toHaveCount(0);
});

test('a clicked project tab is highlighted at once and shows a spinner while its page is slow', async ({
    page,
}) => {
    await page.goto(projectPath);
    const release = await holdPageRequests(page, `${projectPath}/members`);
    const overview = projectTab(page, 'Overview');
    const members = projectTab(page, 'Members');
    await expect(overview).toHaveAttribute('aria-current', 'page');

    await members.click();

    await expect(members).toHaveAttribute('aria-current', 'page');
    await expect(overview).not.toHaveAttribute('aria-current');
    await expect(
        members.getByRole('status', { name: 'Loading' }),
    ).toBeVisible();
    // The project's pages sit below Projects, so the sidebar keeps it highlighted.
    await expect(sidebarLink(page, 'Projects')).toHaveAttribute(
        'aria-current',
        'page',
    );

    release();

    await expect(page).toHaveURL(`${projectPath}/members`);
    await expect(
        page.getByRole('cell', { name: 'member@browser-test.example' }),
    ).toBeVisible();
    await expect(members).toHaveAttribute('aria-current', 'page');
    await expect(overview).not.toHaveAttribute('aria-current');
    await expect(page.getByRole('status', { name: 'Loading' })).toHaveCount(0);
});

test('resting the pointer on a sidebar link loads its page, so the click needs no second request', async ({
    page,
}) => {
    await page.goto('/dashboard');
    const requests = recordPageRequests(page, '/projects');
    const projectsLink = sidebarLink(page, 'Projects');

    const prefetched = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname === '/projects' &&
            isPrefetch(response.request()),
    );
    await projectsLink.hover();
    await prefetched;

    await projectsLink.click();

    await expect(
        page.getByRole('heading', { name: 'Projects', exact: true }),
    ).toBeVisible();
    expect(requests).toHaveLength(1);
    expect(isPrefetch(requests[0])).toBe(true);
});

test('a click while the hover request is still loading is highlighted at once too', async ({
    page,
}) => {
    await page.goto('/dashboard');
    const release = await holdPageRequests(page, '/projects');
    const requests = recordPageRequests(page, '/projects');
    const projectsLink = sidebarLink(page, 'Projects');

    const prefetching = page.waitForRequest(
        (request) =>
            new URL(request.url()).pathname === '/projects' &&
            isPrefetch(request),
    );
    await projectsLink.hover();
    await prefetching;

    await projectsLink.click();

    // Inertia waits for the hover request instead of sending another, and fires no start event for it.
    await expect(projectsLink).toHaveAttribute('aria-current', 'page');
    await expect(
        projectsLink.getByRole('status', { name: 'Loading' }),
    ).toBeVisible();

    release();

    await expect(
        page.getByRole('heading', { name: 'Projects', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('status', { name: 'Loading' })).toHaveCount(0);
    expect(requests).toHaveLength(1);
});

test('saving a form forgets the pages loaded on hover, so they never show data from before the save', async ({
    page,
}) => {
    await page.goto('/settings/notifications');
    const requests = recordPageRequests(page, '/settings/profile');
    const profile = page
        .getByRole('navigation', { name: 'Settings' })
        .getByRole('link', { name: 'Profile' });

    const prefetched = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname === '/settings/profile' &&
            isPrefetch(response.request()),
    );
    await profile.hover();
    await prefetched;

    // Saved unchanged, so the seeded data stays as it is.
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Notification settings saved.')).toBeVisible();

    await profile.click();

    await expect(page).toHaveURL('/settings/profile');
    await expect(profile).toHaveAttribute('aria-current', 'page');
    // The click may start with another hover prefetch, so count every request: without the save, the page
    // loaded on hover would still be fresh and the click would send none.
    expect(requests).toHaveLength(2);
});
