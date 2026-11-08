/**
 * Column widths of the package table, shared by the table and its loading placeholder so both line up.
 * The number column fits the deepest indent and the longest number.
 */
export function packageTableWidths(
    deepestLevel: number,
    longestNumber: number,
): { numberColumn: string; table: string } {
    // The indent of the deepest level, the fold button, the number itself and the cell's padding.
    const numberColumn = `calc(${deepestLevel * 1.25}rem + ${longestNumber}ch + 4rem)`;

    // The fixed columns take 71.5rem; the title gets the rest, but at least 10rem.
    return { numberColumn, table: `calc(${numberColumn} + 81.5rem)` };
}
