import { expect, test as base } from '@playwright/test';

/**
 * Every test also fails on a JavaScript error or a Content Security Policy
 * violation, because the browser reports both as errors.
 */
export const test = base.extend({
    page: async ({ page }, use) => {
        const browserErrors: string[] = [];

        page.on('pageerror', (error) => browserErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') {
                browserErrors.push(message.text());
            }
        });

        await use(page);

        expect(browserErrors).toEqual([]);
    },
});

export { expect };
