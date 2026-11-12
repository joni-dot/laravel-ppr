<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import { queryFor } from '@/lib/packageFilters';
import type { StatusCount } from '@/types';

/**
 * How many packages have each status. Each count above zero opens the list filtered to that status.
 */
const props = defineProps<{
    projectId: number;
    counts: StatusCount[];
}>();

function packagesWith(status: StatusCount['value']) {
    return PackageController.index(props.projectId, {
        query: queryFor({ status }),
    });
}
</script>

<template>
    <ul
        class="flex flex-wrap gap-x-5 gap-y-1 text-sm"
        :aria-label="$t('Packages by status')"
        data-test="status-counts"
    >
        <li
            v-for="status in counts"
            :key="status.value"
            :data-test="`status-${status.value}`"
        >
            <Link
                v-if="status.count > 0"
                :href="packagesWith(status.value)"
                class="inline-flex gap-1 rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span class="text-muted-foreground">{{ status.label }}</span>
                <span
                    class="font-semibold tabular-nums"
                    data-test="status-count"
                >
                    {{ status.count }}
                </span>
            </Link>
            <span v-else class="inline-flex gap-1 text-muted-foreground">
                {{ status.label }}
                <span class="tabular-nums" data-test="status-count">0</span>
            </span>
        </li>
    </ul>
</template>
