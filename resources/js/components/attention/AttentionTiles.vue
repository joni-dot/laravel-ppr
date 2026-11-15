<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import { trans } from '@/lib/i18n';
import { riskBadge } from '@/lib/packageBadges';
import { queryFor, TILE_FILTERS } from '@/lib/packageFilters';
import type { AttentionTile } from '@/lib/packageFilters';
import type { DashboardTiles } from '@/types';

/**
 * A project's headline numbers. Each number above zero opens the package list filtered to exactly the
 * packages it counts.
 */
const props = defineProps<{
    projectId: number;
    tiles: DashboardTiles;
    dueSoonDays: number;
}>();

type Tile = {
    name: AttentionTile;
    label: string;
    // Red and amber show their badge, so the level reads the same as in the package list.
    badgeClass?: string;
};

const tiles = computed<Tile[]>(() => [
    { name: 'red', ...badgeOf('red') },
    { name: 'amber', ...badgeOf('amber') },
    { name: 'overdue', label: trans('Overdue') },
    {
        name: 'dueSoon',
        label: trans('Due within :days days', { days: props.dueSoonDays }),
    },
    { name: 'missingPeople', label: trans('Missing owner or buyer') },
    { name: 'noNeedDate', label: trans('No need date') },
    { name: 'flagged', label: trans('Flagged by hand') },
]);

function badgeOf(level: 'red' | 'amber'): Omit<Tile, 'name'> {
    const badge = riskBadge(level);

    return { label: badge.label, badgeClass: badge.className };
}

function packagesOf(tile: AttentionTile) {
    return PackageController.index(props.projectId, {
        query: queryFor(TILE_FILTERS[tile]),
    });
}
</script>

<template>
    <ul
        class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7"
        data-test="attention-tiles"
    >
        <li
            v-for="tile in tiles"
            :key="tile.name"
            :data-test="`tile-${tile.name}`"
        >
            <component
                :is="props.tiles[tile.name] > 0 ? Link : 'div'"
                :href="
                    props.tiles[tile.name] > 0
                        ? packagesOf(tile.name)
                        : undefined
                "
                class="flex h-full flex-col items-start gap-1.5 rounded-xl border p-3"
                :class="
                    props.tiles[tile.name] > 0
                        ? 'hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                        : 'text-muted-foreground'
                "
            >
                <span
                    class="text-2xl font-semibold tabular-nums"
                    data-test="tile-count"
                >
                    {{ props.tiles[tile.name] }}
                </span>
                <span v-if="tile.badgeClass" :class="tile.badgeClass">
                    {{ tile.label }}
                </span>
                <span v-else class="text-sm">{{ tile.label }}</span>
            </component>
        </li>
    </ul>
</template>
