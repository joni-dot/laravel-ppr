import type { PackageRow } from '@/types';

/**
 * The rows to show from a package tree, in tree order.
 *
 * Without a filter, rows under a folded package are left out. With a filter, every
 * matching row is shown together with the packages above it, so it can be found in
 * the tree, and folding is ignored.
 */
export function visiblePackageRows(
    rows: PackageRow[],
    folded: ReadonlySet<number>,
    matches: ((row: PackageRow) => boolean) | null,
): PackageRow[] {
    return matches === null
        ? rowsOutsideFoldedPackages(rows, folded)
        : matchingRowsWithTheirParents(rows, matches);
}

function rowsOutsideFoldedPackages(
    rows: PackageRow[],
    folded: ReadonlySet<number>,
): PackageRow[] {
    const visible: PackageRow[] = [];
    let hideDeeperThan: number | null = null;

    for (const row of rows) {
        if (hideDeeperThan !== null && row.depth > hideDeeperThan) {
            continue;
        }

        hideDeeperThan = folded.has(row.id) ? row.depth : null;
        visible.push(row);
    }

    return visible;
}

function matchingRowsWithTheirParents(
    rows: PackageRow[],
    matches: (row: PackageRow) => boolean,
): PackageRow[] {
    const byId = new Map(rows.map((row) => [row.id, row]));
    const shown = new Set<number>();

    for (const row of rows) {
        if (!matches(row)) {
            continue;
        }

        let current: PackageRow | undefined = row;

        while (current && !shown.has(current.id)) {
            shown.add(current.id);
            current =
                current.parentId === null
                    ? undefined
                    : byId.get(current.parentId);
        }
    }

    return rows.filter((row) => shown.has(row.id));
}

/**
 * The ids of packages that have sub-packages.
 */
export function packagesWithChildren(rows: PackageRow[]): Set<number> {
    return new Set(
        rows
            .map((row) => row.parentId)
            .filter((id): id is number => id !== null),
    );
}
