// Matches database/seeders/BrowserTestSeeder.php. The database is emptied before every run.
export const password = 'password';

export const users = {
    admin: 'admin@browser-test.example',
    member: 'member@browser-test.example',
} as const;

// Has no saved sign-in; a test that needs them signs in itself.
export const manager = 'manager@browser-test.example';

export const projects = {
    // The member is a viewer here.
    visible: { id: 1, name: 'Browser Project', code: 'BP1' },
    // The member does not belong to this one.
    hidden: { id: 2, name: 'Hidden Project', code: 'HP2' },
    // For the package tests. The member is a viewer here too.
    packages: { id: 3, name: 'Package Project', code: 'PP3' },
    // For the milestone list tests, which change its milestones. The member is a viewer here.
    milestones: { id: 4, name: 'Milestone Project', code: 'MP4' },
    // For the notification tests. The member is a buyer here.
    notifications: { id: 5, name: 'Notification Project', code: 'NP5' },
    // For the risk tests. The member is a viewer here.
    risk: { id: 6, name: 'Risk Project', code: 'RP6' },
    // For the risk check tests. The member and the admin are package owners here.
    riskCheck: { id: 7, name: 'Risk Check Project', code: 'RC7' },
    // 300 packages, "Long list item 001" to "Long list item 300". The member is a viewer here.
    longList: {
        id: 8,
        name: 'Long List Project',
        code: 'LL8',
        packageCount: 300,
    },
} as const;

// The packages of the risk check project. Both have plenty of time before their need date at the start.
export const riskCheckPackages = {
    // The member owns it.
    watchedTank: {
        number: 'RC7-0001',
        title: 'Watched tank',
        needDate: '2099-06-01',
        forecast: '2099-01-01',
    },
    // The admin owns it. Its order was expected two days before the database was filled, and its delivery
    // is expected three days after, so it is red and the admin starts with three notifications about it.
    remindedCompressor: {
        number: 'RC7-0002',
        title: 'Reminded compressor',
        orderDaysAgo: 2,
        deliveryInDays: 3,
    },
} as const;

// The packages of the risk project, in list order. "Overdue pump" is a sub-package of "Main skid".
export const riskPackages = {
    mainSkid: { id: 3, title: 'Main skid' },
    overduePump: { id: 4, title: 'Overdue pump', overdueOrder: '2020-01-15' },
    unownedCrate: { id: 5, title: 'Unowned crate' },
    calmValve: { id: 6, title: 'Calm valve' },
} as const;

// The only package at the start, in the package project.
export const seededPackage = {
    id: 1,
    number: 'PP3-MEC-0001',
    title: 'Seeded pump',
    // Its only milestone date at the start.
    installedForecast: '2027-09-30',
} as const;

// The only package of the notification project at the start. Nobody owns or buys it.
export const notifiedPackage = {
    number: 'NP5-MEC-0001',
    title: 'Notified valve',
} as const;
