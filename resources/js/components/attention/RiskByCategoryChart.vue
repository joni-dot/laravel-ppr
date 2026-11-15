<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import { trans } from '@/lib/i18n';
import { queryFor } from '@/lib/packageFilters';
import type { CategoryRiskCount } from '@/types';

/**
 * Open packages per category as horizontal bars split by risk level. Bars share one scale, so a longer
 * bar is a bigger category. The numbers are also written out, and the red and amber ones open the list
 * filtered to those packages.
 */
const props = defineProps<{
    projectId: number;
    categories: CategoryRiskCount[];
}>();

type Level = 'red' | 'amber' | 'none';

// Not only colour tells them apart: each has a word, and the segments have a gap between them.
const LEVELS: { level: Level; segmentClass: string }[] = [
    { level: 'red', segmentClass: 'bg-destructive dark:bg-destructive/80' },
    { level: 'amber', segmentClass: 'bg-amber-400 dark:bg-amber-500' },
    { level: 'none', segmentClass: 'bg-muted-foreground/30' },
];

const largestTotal = computed(() =>
    Math.max(1, ...props.categories.map(totalOf)),
);

function totalOf(category: CategoryRiskCount): number {
    return category.red + category.amber + category.none;
}

function widthOf(count: number): string {
    return `${(count / largestTotal.value) * 100}%`;
}

function nameOf(category: CategoryRiskCount): string {
    return category.code === null
        ? category.name
        : trans(':code – :name', { code: category.code, name: category.name });
}

function levelInWords(level: Level): string {
    switch (level) {
        case 'red':
            return trans('Red');
        case 'amber':
            return trans('Amber');
        case 'none':
            return trans('No risk');
    }
}

// Packages without a category have no list filter, and packages not at risk have none of their own.
function linkOf(category: CategoryRiskCount, level: Level) {
    if (
        level === 'none' ||
        category.categoryId === null ||
        category[level] === 0
    ) {
        return null;
    }

    return PackageController.index(props.projectId, {
        query: queryFor({ category: category.categoryId, risk: level }),
    });
}

function sentenceOf(category: CategoryRiskCount): string {
    return trans(':category: red :red, amber :amber, no risk :none.', {
        category: nameOf(category),
        red: category.red,
        amber: category.amber,
        none: category.none,
    });
}
</script>

<template>
    <p v-if="categories.length === 0" class="text-sm text-muted-foreground">
        {{ $t('No open packages.') }}
    </p>
    <div v-else class="grid gap-4">
        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs" aria-hidden="true">
            <li
                v-for="{ level, segmentClass } in LEVELS"
                :key="level"
                class="inline-flex items-center gap-1.5"
            >
                <span class="size-3 rounded-sm" :class="segmentClass" />
                {{ levelInWords(level) }}
            </li>
        </ul>

        <ul class="grid gap-3">
            <li
                v-for="category in categories"
                :key="category.categoryId ?? 'none'"
                data-test="category-bar"
                :data-category-id="category.categoryId ?? ''"
            >
                <p class="sr-only">{{ sentenceOf(category) }}</p>
                <div
                    class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm"
                >
                    <span class="truncate font-medium" aria-hidden="true">
                        {{ nameOf(category) }}
                    </span>
                    <span class="flex gap-3 text-xs tabular-nums">
                        <template v-for="{ level } in LEVELS" :key="level">
                            <Link
                                v-if="linkOf(category, level)"
                                :href="linkOf(category, level)!"
                                class="rounded-sm underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :aria-label="
                                    $t(':level packages in :category: :count', {
                                        level: levelInWords(level),
                                        category: nameOf(category),
                                        count: category[level],
                                    })
                                "
                                :data-test="`count-${level}`"
                            >
                                {{
                                    $t(':level: :count', {
                                        level: levelInWords(level),
                                        count: category[level],
                                    })
                                }}
                            </Link>
                            <span
                                v-else
                                class="text-muted-foreground"
                                aria-hidden="true"
                                :data-test="`count-${level}`"
                            >
                                {{
                                    $t(':level: :count', {
                                        level: levelInWords(level),
                                        count: category[level],
                                    })
                                }}
                            </span>
                        </template>
                    </span>
                </div>
                <!-- The links above are the ones for the keyboard and screen readers; these are for the mouse. -->
                <div
                    class="mt-1 flex h-3 gap-0.5 overflow-hidden"
                    aria-hidden="true"
                >
                    <template
                        v-for="{ level, segmentClass } in LEVELS"
                        :key="level"
                    >
                        <component
                            :is="linkOf(category, level) ? Link : 'div'"
                            v-if="category[level] > 0"
                            :href="linkOf(category, level) ?? undefined"
                            tabindex="-1"
                            class="h-full min-w-1 first:rounded-l-sm last:rounded-r-sm"
                            :class="[
                                segmentClass,
                                linkOf(category, level) && 'hover:opacity-80',
                            ]"
                            :style="{ width: widthOf(category[level]) }"
                            :data-test="`segment-${level}`"
                        />
                    </template>
                </div>
            </li>
        </ul>
    </div>
</template>
