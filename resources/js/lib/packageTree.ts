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
    matchingIds: ReadonlySet<number> | null,
): PackageRow[] {
    return matchingIds === null
        ? rowsOutsideFoldedPackages(rows, folded)
        : matchingRowsWithTheirParents(rows, matchingIds);
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
    matchingIds: ReadonlySet<number>,
): PackageRow[] {
    const byId = new Map(rows.map((row) => [row.id, row]));
    const shown = new Set<number>();

    for (const id of matchingIds) {
        let current = byId.get(id);

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
