<script setup lang="ts">
import PackageTreeColumns from '@/components/packages/PackageTreeColumns.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { packageTableWidths } from '@/lib/packageTableWidths';

/**
 * Stands in for the package table while its rows load: the same headings and columns, and a few grey
 * rows about as tall as real ones, so the page doesn't jump when the packages arrive.
 */

// Room for a sub-package number such as "P100-ELE-0001.01".
const widths = packageTableWidths(1, 16);

// Bars of different lengths, so the rows read as a table of text rather than a grid.
const titleWidths = ['w-48', 'w-36', 'w-56', 'w-40', 'w-44', 'w-32'];
const ROW_COUNT = 8;

function titleWidth(row: number): string {
    return titleWidths[row % titleWidths.length];
}
</script>

<template>
    <div class="overflow-x-auto rounded-xl border">
        <span class="sr-only" role="status">{{ $t('Loading…') }}</span>
        <table
            class="w-full table-fixed text-sm"
            :style="{ minWidth: widths.table }"
            aria-busy="true"
            data-test="package-table-skeleton"
        >
            <PackageTreeColumns :number-column-width="widths.numberColumn" />
            <tbody class="divide-y" aria-hidden="true">
                <tr v-for="row in ROW_COUNT" :key="row" class="h-13">
                    <td class="px-4 py-2">
                        <Skeleton class="ml-7 h-4 w-28" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton
                            class="h-4 max-w-full"
                            :class="titleWidth(row)"
                        />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="h-5 w-16 rounded-full" />
                    </td>
                    <td class="px-4 py-2" />
                    <td class="px-4 py-2">
                        <Skeleton class="h-4 w-28" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="h-4 w-20" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="h-4 w-20" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="ml-auto h-4 w-8" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="h-4 w-16" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="h-4 w-20" />
                    </td>
                    <td class="px-4 py-2">
                        <Skeleton class="h-4 w-20" />
                    </td>
                    <td class="px-4 py-2" />
                </tr>
            </tbody>
        </table>
    </div>
</template>
