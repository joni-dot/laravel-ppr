<script setup lang="ts">
import { ChevronDown, ChevronRight, Pencil, Plus } from '@lucide/vue';
import { useWindowVirtualizer } from '@tanstack/vue-virtual';
import { useResizeObserver } from '@vueuse/core';
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import PackageTreeColumns from '@/components/packages/PackageTreeColumns.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import { buttonVariants } from '@/components/ui/button';
import { visitLinkInApp } from '@/lib/inAppLinks';
import { packageRowViews } from '@/lib/packageRowView';
import { packageTableWidths } from '@/lib/packageTableWidths';
import { cn } from '@/lib/utils';
import type { PackageRow, ProjectCategory, PurchaseType } from '@/types';

/**
 * The package tree as a table. A project can have thousands of packages, so only the rows on screen and
 * a few around them are drawn; empty space above and below stands in for the rest, so the page scrolls
 * as if every row were there.
 */
const props = defineProps<{
    projectId: number;
    // Every package of the project, so the number column fits the longest number however the list is filtered.
    packages: PackageRow[];
    // The packages to show, in tree order.
    rows: PackageRow[];
    // While filtering: the packages that match. The others are shown only to place a match in the tree.
    matchingIds: ReadonlySet<number> | null;
    parents: ReadonlySet<number>;
    folded: ReadonlySet<number>;
    purchaseTypeLabels: ReadonlyMap<PurchaseType, string>;
    categories: ProjectCategory[];
    canCreate: boolean;
    // The package with an open "add a sub-package" form below it.
    addingUnderId: number | null;
    // The page props the add form reloads; see QuickAddPackage.
    reloadOnly?: string[];
}>();

const emit = defineEmits<{
    toggleFolded: [row: PackageRow];
    addUnder: [row: PackageRow];
    closeAdding: [];
}>();

// A typical row. Rows are measured once drawn, so this only has to be close.
const ESTIMATED_ROW_HEIGHT = 60;
const COLUMN_COUNT = 12;

const foldButtonClass = cn(
    buttonVariants({ variant: 'ghost', size: 'icon' }),
    'size-6',
);
const iconButtonClass = buttonVariants({ variant: 'ghost', size: 'icon' });

type ListItem = { key: string; row: PackageRow; isAddForm: boolean };

const items = computed<ListItem[]>(() => {
    const list: ListItem[] = [];

    for (const row of props.rows) {
        list.push({ key: `package-${row.id}`, row, isAddForm: false });

        if (row.id === props.addingUnderId) {
            list.push({ key: `add-under-${row.id}`, row, isAddForm: true });
        }
    }

    return list;
});

const table = useTemplateRef<HTMLTableElement>('table');
const body = useTemplateRef<HTMLTableSectionElement>('body');
// Where the first row starts, measured from the top of the page.
const listOffset = ref(0);
const page = ref<HTMLElement | null>(null);

function updateListOffset(): void {
    if (body.value) {
        listOffset.value =
            body.value.getBoundingClientRect().top + window.scrollY;
    }
}

// Anything above the list, such as an error under the quick-add form, can move it down the page.
useResizeObserver(page, updateListOffset);

onMounted(() => {
    page.value = document.body;
    updateListOffset();
});

const virtualizer = useWindowVirtualizer(
    computed(() => ({
        count: items.value.length,
        estimateSize: () => ESTIMATED_ROW_HEIGHT,
        getItemKey: (index: number) => items.value[index].key,
        overscan: 10,
        scrollMargin: listOffset.value,
    })),
);

const viewOf = computed(() =>
    packageRowViews(props.projectId, props.purchaseTypeLabels),
);

const drawnItems = computed(() =>
    virtualizer.value.getVirtualItems().map((virtualItem) => {
        const item = items.value[virtualItem.index];

        return {
            ...item,
            index: virtualItem.index,
            view: viewOf.value(item.row),
        };
    }),
);

const spaceAbove = computed(() => {
    const first = virtualizer.value.getVirtualItems()[0];

    return first ? first.start - listOffset.value : 0;
});

const spaceBelow = computed(() => {
    const drawn = virtualizer.value.getVirtualItems();
    const last = drawn[drawn.length - 1];

    return last
        ? virtualizer.value.getTotalSize() - (last.end - listOffset.value)
        : 0;
});

const widths = computed(() => {
    let deepest = 0;
    let longest = 0;

    for (const row of props.packages) {
        deepest = Math.max(deepest, row.depth);
        longest = Math.max(longest, row.number.length);
    }

    return packageTableWidths(deepest, longest);
});

function measure(element: Element | ComponentPublicInstance | null): void {
    if (element instanceof Element) {
        virtualizer.value.measureElement(element);
    }
}

function isDimmed(row: PackageRow): boolean {
    return props.matchingIds !== null && !props.matchingIds.has(row.id);
}

function canFold(row: PackageRow): boolean {
    return props.matchingIds === null && props.parents.has(row.id);
}

/**
 * Brings the top of the list into view when the page has been scrolled past it, for example after
 * the filters change and the rows the user was looking at are gone.
 */
function scrollToStart(): void {
    const top = table.value?.getBoundingClientRect().top ?? 0;

    if (top < 0) {
        window.scrollBy({ top });
    }
}

defineExpose({ scrollToStart });
</script>

<template>
    <!-- A size container, so the add form below can be as wide as the visible part of the table. -->
    <div class="@container overflow-x-auto rounded-xl border">
        <table
            ref="table"
            class="w-full table-fixed text-sm"
            :style="{ minWidth: widths.table }"
            :aria-rowcount="items.length + 1"
        >
            <PackageTreeColumns :number-column-width="widths.numberColumn" />
            <tbody ref="body" class="divide-y" @click="visitLinkInApp">
                <tr v-if="spaceAbove > 0" aria-hidden="true">
                    <td
                        :colspan="COLUMN_COUNT"
                        class="p-0"
                        :style="{ height: `${spaceAbove}px` }"
                    />
                </tr>
                <template v-for="item in drawnItems" :key="item.key">
                    <tr
                        v-if="!item.isAddForm"
                        :ref="measure"
                        :data-index="item.index"
                        :aria-rowindex="item.index + 2"
                        :class="{ 'text-muted-foreground': isDimmed(item.row) }"
                    >
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div
                                class="flex items-center gap-1"
                                :style="{
                                    paddingLeft: `${item.row.depth * 1.25}rem`,
                                }"
                            >
                                <button
                                    v-if="canFold(item.row)"
                                    type="button"
                                    :class="foldButtonClass"
                                    :aria-expanded="!folded.has(item.row.id)"
                                    :aria-label="
                                        folded.has(item.row.id)
                                            ? item.view.showSubPackagesLabel
                                            : item.view.hideSubPackagesLabel
                                    "
                                    @click="emit('toggleFolded', item.row)"
                                >
                                    <ChevronRight
                                        v-if="folded.has(item.row.id)"
                                    />
                                    <ChevronDown v-else />
                                </button>
                                <span v-else class="size-6 shrink-0" />
                                <span class="font-mono">{{
                                    item.row.number
                                }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <a
                                :href="item.view.showUrl"
                                data-in-app
                                class="font-medium hover:underline"
                            >
                                {{ item.row.title }}
                            </a>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                data-slot="badge"
                                :class="item.view.status.className"
                            >
                                {{ item.view.status.label }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-if="item.view.risk"
                                :title="item.view.risk.summary"
                            >
                                <span
                                    data-slot="badge"
                                    data-test="risk-badge"
                                    :class="item.view.risk.className"
                                >
                                    {{ item.view.risk.label }}
                                </span>
                                <span class="sr-only">{{
                                    item.view.risk.summary
                                }}</span>
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <template v-if="item.row.nextMilestoneName">
                                <div>{{ item.row.nextMilestoneName }}</div>
                                <div
                                    v-if="item.view.nextMilestoneDate"
                                    class="text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ item.view.nextMilestoneDate }}
                                </div>
                            </template>
                            <template v-else>–</template>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ item.view.needDate }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ item.view.expectedNeedDate }}
                        </td>
                        <td
                            class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            :class="{
                                'text-destructive': item.view.float?.isLate,
                            }"
                        >
                            <template v-if="item.view.float === null">
                                –
                            </template>
                            <span v-else :title="item.view.float.words">
                                <span aria-hidden="true">{{
                                    item.view.float.short
                                }}</span>
                                <span class="sr-only">{{
                                    item.view.float.words
                                }}</span>
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            {{ item.view.purchaseType }}
                        </td>
                        <td class="px-4 py-2">{{ item.row.ownerName }}</td>
                        <td class="px-4 py-2">{{ item.row.buyerName }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button
                                v-if="canCreate"
                                type="button"
                                :class="iconButtonClass"
                                :aria-label="item.view.addSubPackageLabel"
                                @click="emit('addUnder', item.row)"
                            >
                                <Plus />
                            </button>
                            <a
                                v-if="item.row.canEdit"
                                :href="item.view.editUrl"
                                data-in-app
                                :class="iconButtonClass"
                                :aria-label="item.view.editLabel"
                            >
                                <Pencil />
                            </a>
                        </td>
                    </tr>
                    <tr
                        v-else
                        :ref="measure"
                        :data-index="item.index"
                        :aria-rowindex="item.index + 2"
                    >
                        <td
                            :colspan="COLUMN_COUNT"
                            class="bg-muted/30 px-4 py-3"
                        >
                            <!-- Stays in view when the table is scrolled sideways, so its buttons can always be reached. -->
                            <div
                                class="sticky left-4 w-[calc(100cqw-2rem)]"
                                :style="{
                                    paddingLeft: `${(item.row.depth + 1) * 1.25}rem`,
                                }"
                            >
                                <QuickAddPackage
                                    :project-id="projectId"
                                    :categories="categories"
                                    :parent="item.row"
                                    :reload-only="reloadOnly"
                                    autofocus
                                    closable
                                    @close="emit('closeAdding')"
                                />
                            </div>
                        </td>
                    </tr>
                </template>
                <tr v-if="spaceBelow > 0" aria-hidden="true">
                    <td
                        :colspan="COLUMN_COUNT"
                        class="p-0"
                        :style="{ height: `${spaceBelow}px` }"
                    />
                </tr>
                <tr v-if="items.length === 0">
                    <td
                        :colspan="COLUMN_COUNT"
                        class="px-4 py-10 text-center text-muted-foreground"
                    >
                        <slot name="empty" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
