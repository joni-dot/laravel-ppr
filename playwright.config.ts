/// <reference types="node" />

import { defineConfig, devices } from '@playwright/test';

const roles = ['admin', 'member'] as const;

export default defineConfig({
    testDir: 'tests/Browser',
    outputDir: 'tests/Browser/.output',
    // All tests share one database, so they run one at a time.
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    reporter: [['list']],
    use: {
        baseURL: 'http://localhost:8001',
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        ...devices['Desktop Chrome'],
    },
    projects: [
        { name: 'sign-in', testMatch: 'sign-in.setup.ts' },
        { name: 'signed-out', testMatch: 'login.spec.ts' },
        ...roles.map((role) => ({
            name: role,
            testMatch: `${role}/*.spec.ts`,
            dependencies: ['sign-in'],
            use: { storageState: `tests/Browser/.auth/${role}.json` },
        })),
    ],
});
