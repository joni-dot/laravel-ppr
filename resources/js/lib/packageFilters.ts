import type { PackageRow, PackageStatus, RiskReason } from '@/types';

/**
 * The package list's filters, and what a dashboard number links to. Each dashboard number is defined in
 * app/Dashboard/ProjectAttention.php, and the filter it links to here must show exactly the packages it
 * counts; change both together. A browser test opens every number's list and compares the counts.
 */

// Must equal ProjectAttention::DUE_SOON_DAYS.
export const DUE_SOON_DAYS = 14;

// "atRisk" is amber or red.
export type RiskFilter = '' | 'atRisk' | 'amber' | 'red';
// "people" is no owner or buyer, or a deactivated one.
export type ReasonFilter =
    | ''
    | 'overdue'
    | 'people'
    | 'no_need_date'
    | 'manual_flag';
// "soon" is a milestone not done yet expected within DUE_SOON_DAYS, today included.
export type DueFilter = '' | 'soon';

export type PackageFilters = {
    search: string;
    status: PackageStatus | '';
    category: number | '';
    risk: RiskFilter;
    reason: ReasonFilter;
    due: DueFilter;
};

export const NO_FILTERS: Readonly<PackageFilters> = {
    search: '',
    status: '',
    category: '',
    risk: '',
    reason: '',
    due: '',
};

// The dashboard numbers that count packages with something needing attention.
export type AttentionTile =
    | 'red'
    | 'amber'
    | 'overdue'
    | 'dueSoon'
    | 'missingPeople'
    | 'noNeedDate'
    | 'flagged';

// The filter each number links to: it shows exactly the packages the number counts.
export const TILE_FILTERS: Readonly<
    Record<AttentionTile, Partial<PackageFilters>>
> = {
    red: { risk: 'red' },
    amber: { risk: 'amber' },
    overdue: { reason: 'overdue' },
    dueSoon: { due: 'soon' },
    missingPeople: { reason: 'people' },
    noNeedDate: { reason: 'no_need_date' },
    flagged: { reason: 'manual_flag' },
};

const RISK_FILTERS: readonly RiskFilter[] = ['atRisk', 'amber', 'red'];
const REASON_FILTERS: readonly ReasonFilter[] = [
    'overdue',
    'people',
    'no_need_date',
    'manual_flag',
];
const PEOPLE_REASONS: readonly RiskReason[] = [
    'no_owner',
    'owner_deactivated',
    'no_buyer',
    'buyer_deactivated',
];
const FINISHED_STATUSES: readonly PackageStatus[] = ['closed', 'cancelled'];

/**
 * The filters a page address asks for. Values the page doesn't know (an old link, a typo, a category
 * from another project) are ignored rather than showing an empty list.
 */
export function filtersFromQuery(
    query: URLSearchParams,
    known: { statuses: readonly string[]; categoryIds: readonly number[] },
): PackageFilters {
    const status = query.get('status') ?? '';
    const category = Number(query.get('category'));

    return {
        search: (query.get('search') ?? '').slice(0, 100),
        status: known.statuses.includes(status)
            ? (status as PackageStatus)
            : '',
        category: known.categoryIds.includes(category) ? category : '',
        risk: oneOf(query.get('risk'), RISK_FILTERS),
        reason: oneOf(query.get('reason'), REASON_FILTERS),
        due: query.get('due') === 'soon' ? 'soon' : '',
    };
}

/**
 * The address parameters for the filters, leaving out the ones not in use.
 */
export function filtersToQuery(
    filters: PackageFilters,
): Record<string, string> {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') {
            query[key] = String(value);
        }
    }

    return query;
}

/**
 * The address parameters for a link to the list with only these filters, such as `{ risk: 'red' }`.
 */
export function queryFor(
    filters: Partial<PackageFilters>,
): Record<string, string> {
    return filtersToQuery({ ...NO_FILTERS, ...filters });
}

export function isFiltering(filters: PackageFilters): boolean {
    return Object.values(filters).some((value) => value !== '');
}

/**
 * A test for one row against the filters. The search text is matched case-insensitively against the
 * number, title, owner and buyer.
 *
 * @param today "YYYY-MM-DD" in UTC, as the server counts days
 */
export function packageMatcher(
    filters: PackageFilters,
    today: string = todayUtc(),
): (row: PackageRow) => boolean {
    const search = filters.search.trim().toLocaleLowerCase();
    const dueBy = addDays(today, DUE_SOON_DAYS);

    return (row) =>
        (search === '' || searchTextOf(row).includes(search)) &&
        (filters.status === '' || row.status === filters.status) &&
        (filters.category === '' || row.categoryId === filters.category) &&
        matchesRisk(row, filters.risk) &&
        matchesReason(row, filters.reason) &&
        (filters.due === '' || isDueSoon(row, today, dueBy));
}

export function todayUtc(): string {
    return new Date().toISOString().slice(0, 10);
}

function matchesRisk(row: PackageRow, risk: RiskFilter): boolean {
    switch (risk) {
        case '':
            return true;
        case 'atRisk':
            return row.risk !== null;
        case 'amber':
        case 'red':
            return row.risk?.level === risk;
    }
}

function matchesReason(row: PackageRow, reason: ReasonFilter): boolean {
    const reasons = row.risk?.reasons ?? [];

    switch (reason) {
        case '':
            return true;
        case 'people':
            return reasons.some((found) => PEOPLE_REASONS.includes(found));
        default:
            return reasons.includes(reason);
    }
}

function isDueSoon(row: PackageRow, today: string, dueBy: string): boolean {
    return (
        !FINISHED_STATUSES.includes(row.status) &&
        row.dueDate !== null &&
        row.dueDate >= today &&
        row.dueDate <= dueBy
    );
}

// Each row's searchable text, lowercased once instead of on every search.
const searchTexts = new WeakMap<PackageRow, string>();

function searchTextOf(row: PackageRow): string {
    let text = searchTexts.get(row);

    if (text === undefined) {
        text = [row.number, row.title, row.ownerName, row.buyerName]
            .filter((part) => part !== null)
            .join('\n')
            .toLocaleLowerCase();
        searchTexts.set(row, text);
    }

    return text;
}

function addDays(date: string, days: number): string {
    const result = new Date(`${date}T00:00:00Z`);
    result.setUTCDate(result.getUTCDate() + days);

    return result.toISOString().slice(0, 10);
}

function oneOf<T extends string>(
    value: string | null,
    allowed: readonly T[],
): T | '' {
    return allowed.includes(value as T) ? (value as T) : '';
}
