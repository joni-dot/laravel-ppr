// Matches database/seeders/BrowserTestSeeder.php. The database is emptied before every run.
export const password = 'password';

export const users = {
    admin: 'admin@browser-test.example',
    member: 'member@browser-test.example',
} as const;

export const projects = {
    // The member is a viewer here.
    visible: { id: 1, name: 'Browser Project', code: 'BP1' },
    // The member does not belong to this one.
    hidden: { id: 2, name: 'Hidden Project', code: 'HP2' },
    // For the package tests. The member is a viewer here too.
    packages: { id: 3, name: 'Package Project', code: 'PP3' },
    // For the milestone list tests, which change its milestones. The member is a viewer here.
    milestones: { id: 4, name: 'Milestone Project', code: 'MP4' },
} as const;

// The only package at the start, in the package project.
export const seededPackage = {
    id: 1,
    number: 'PP3-MEC-0001',
    title: 'Seeded pump',
    // Its only milestone date at the start.
    installedForecast: '2027-09-30',
} as const;
