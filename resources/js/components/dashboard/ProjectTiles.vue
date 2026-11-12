<script setup lang="ts">
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import CountLink from '@/components/dashboard/CountLink.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { trans } from '@/lib/i18n';
import { riskBadge } from '@/lib/packageBadges';
import { queryFor, TILE_FILTERS } from '@/lib/packageFilters';
import type { AttentionTile } from '@/lib/packageFilters';
import type { DashboardTiles } from '@/types';

/**
 * A project's headline numbers. Each opens the package list filtered to exactly the packages it counts;
 * the counting rules are in app/Dashboard/ProjectAttention.php, the filters in lib/packageFilters.ts.
 */
defineProps<{
    projectId: number;
    // Missing while the numbers load.
    tiles?: DashboardTiles;
}>();

type TileName = AttentionTile;

const MAIN_TILES: { name: TileName; test: string }[] = [
    { name: 'red', test: 'tile-red' },
    { name: 'amber', test: 'tile-amber' },
    { name: 'overdue', test: 'tile-overdue' },
    { name: 'dueSoon', test: 'tile-due-soon' },
];

function packagesOf(projectId: number, tile: TileName) {
    return PackageController.index(projectId, {
        query: queryFor(TILE_FILTERS[tile]),
    });
}

// Red and amber look like the risk badges and say their level in words, so colour isn't needed to read them.
function mainLabel(tile: TileName): { label: string; className: string } {
    switch (tile) {
        case 'red':
        case 'amber':
            return riskBadge(tile);
        case 'overdue':
            return {
                label: trans('Overdue'),
                className: 'text-muted-foreground',
            };
        default:
            return {
                label: trans('Due soon'),
                className: 'text-muted-foreground',
            };
    }
}
</script>

<template>
    <div
        v-if="tiles === undefined"
        class="flex flex-wrap gap-6"
        aria-busy="true"
        data-test="project-tiles-skeleton"
    >
        <span class="sr-only" role="status">{{ $t('Loading…') }}</span>
        <div
            v-for="tile in MAIN_TILES"
            :key="tile.name"
            class="space-y-1.5"
            aria-hidden="true"
        >
            <Skeleton class="h-7 w-8 motion-reduce:animate-none" />
            <Skeleton class="h-5 w-16 motion-reduce:animate-none" />
        </div>
    </div>

    <div v-else class="flex flex-col gap-2 sm:items-end">
        <div class="flex flex-wrap gap-6 text-sm">
            <CountLink
                v-for="tile in MAIN_TILES"
                :key="tile.name"
                :count="tiles[tile.name]"
                :href="packagesOf(projectId, tile.name)"
                class="flex flex-col items-start gap-1"
                :data-test="tile.test"
            >
                <span class="text-xl font-semibold tabular-nums">
                    {{ tiles[tile.name] }}
                </span>
                <span :class="mainLabel(tile.name).className">
                    {{ mainLabel(tile.name).label }}
                </span>
            </CountLink>
        </div>
        <p class="flex flex-wrap gap-x-4 text-xs text-muted-foreground">
            <CountLink
                :count="tiles.missingPeople"
                :href="packagesOf(projectId, 'missingPeople')"
                data-test="tile-missing-people"
            >
                {{
                    $t('Missing owner or buyer: :count', {
                        count: tiles.missingPeople,
                    })
                }}
            </CountLink>
            <CountLink
                :count="tiles.noNeedDate"
                :href="packagesOf(projectId, 'noNeedDate')"
                data-test="tile-no-need-date"
            >
                {{ $t('No need date: :count', { count: tiles.noNeedDate }) }}
            </CountLink>
            <CountLink
                :count="tiles.flagged"
                :href="packagesOf(projectId, 'flagged')"
                data-test="tile-flagged"
            >
                {{
                    $t('Flagged as high risk: :count', { count: tiles.flagged })
                }}
            </CountLink>
        </p>
    </div>
</template>
