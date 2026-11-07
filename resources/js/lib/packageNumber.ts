/**
 * A live preview of package numbers while a project manager edits the numbering.
 * Real numbers are made on the server by app/Numbering/PackageNumberFormat.php;
 * this follows the same rules, and a browser test checks that both agree.
 */
export type PackageNumberParts = {
    prefix: string | null;
    projectCode: string | null;
    categoryCode: string | null;
    separator: string;
    padding: number;
    runningNumber: number;
};

export function formatPackageNumber(parts: PackageNumberParts): string {
    return [
        parts.prefix,
        parts.projectCode,
        parts.categoryCode,
        pad(parts.runningNumber, parts.padding),
    ]
        .filter((part): part is string => part !== null && part !== '')
        .join(parts.separator);
}

export function formatSubPackageNumber(
    packageNumber: string,
    separator: string,
    subNumber: number,
    padding: number,
): string {
    return `${packageNumber}${separator}${pad(subNumber, padding)}`;
}

/**
 * The running number the next package gets: the first number, unless numbers
 * at or above it were already given. Same rule as Project::nextRunningNumber().
 */
export function nextRunningNumber(
    start: number,
    lastGiven: number | null,
): number {
    return Math.max(start, (lastGiven ?? 0) + 1);
}

function pad(value: number, length: number): string {
    return String(value).padStart(length, '0');
}
