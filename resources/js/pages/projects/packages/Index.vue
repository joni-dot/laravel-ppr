<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onUnmounted, ref, useTemplateRef, watch } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import PackageTreeTable from '@/components/packages/PackageTreeTable.vue';
import PackageTreeTableSkeleton from '@/components/packages/PackageTreeTableSkeleton.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { trans } from '@/lib/i18n';
import {
    DUE_SOON_DAYS,
    filtersFromQuery,
    filtersToQuery,
    isFiltering as hasFilters,
    NO_FILTERS,
    packageMatcher,
} from '@/lib/packageFilters';
import type { PackageFilters, ReasonFilter } from '@/lib/packageFilters';
import { packagesWithChildren, visiblePackageRows } from '@/lib/packageTree';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    Option,
    PackageRow,
    PackageStatus,
    ProjectCategory,
    ProjectHeader,
    PurchaseType,
} from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader }) =>
        projectBreadcrumbs(props.project, {
            title: trans('Packages'),
            href: PackageController.index(props.project.id),
        }),
});

const props = defineProps<{
    project: ProjectHeader;
    // Deferred: missing while the page first loads; see PackageController::index().
    packages?: PackageRow[];
    // Deferred with the tree: the UTC day its due dates were worked out for.
    today?: string;
    categories: ProjectCategory[];
    categoryRequired: boolean;
    statuses: Option<PackageStatus>[];
    purchaseTypes: Option<PurchaseType>[];
    can: { create: boolean };
}>();

// Adding a package reloads the tree with it, instead of dropping the tree and loading it again.
const RELOAD_AFTER_ADDING = ['packages', 'today'];

// Searching waits for a short pause in typing, so the list isn't filtered again for every letter.
const SEARCH_DELAY_MS = 150;

const page = usePage();

// The filters come from the page address, so a dashboard number or a shared link opens the list filtered.
const filters = ref<PackageFilters>(
    filtersFromQuery(new URL(page.url, window.location.origin).searchParams, {
        statuses: props.statuses.map((status) => status.value),
        categoryIds: props.categories.map((category) => category.id),
    }),
);
const searchInput = ref(filters.value.search);
const folded = ref(new Set<number>());
const addingUnderId = ref<number | null>(null);
const table = useTemplateRef<InstanceType<typeof PackageTreeTable>>('table');

watchDebounced(
    searchInput,
    (value) => {
        filters.value.search = value;
    },
    { debounce: SEARCH_DELAY_MS },
);

// One select for the reasons and the due date, which the address keeps as two filters.
type AttentionFilter = ReasonFilter | 'due_soon';

const attentionFilter = computed<AttentionFilter>({
    get: () =>
        filters.value.due === 'soon' ? 'due_soon' : filters.value.reason,
    set: (value) => {
        filters.value.reason = value === 'due_soon' ? '' : value;
        filters.value.due = value === 'due_soon' ? 'soon' : '';
    },
});

// Empty until the tree has loaded, so the filters can be used meanwhile.
const allPackages = computed(() => props.packages ?? []);
const hasLoaded = computed(() => props.packages !== undefined);

const parents = computed(() => packagesWithChildren(allPackages.value));

const purchaseTypeLabels = computed(
    () => new Map(props.purchaseTypes.map((type) => [type.value, type.label])),
);

const isFiltering = computed(() => hasFilters(filters.value));

const matchingIds = computed(() => {
    if (!isFiltering.value) {
        return null;
    }

    // The server's day, not the browser's clock, so "due soon" counts what the dashboard counts.
    const matches = packageMatcher(filters.value, props.today);

    return new Set(allPackages.value.filter(matches).map((row) => row.id));
});

const rows = computed(() =>
    visiblePackageRows(allPackages.value, folded.value, matchingIds.value),
);

watch(filters, () => table.value?.scrollToStart(), { deep: true });

// Waits for the tree: its request carries the address it was sent from, and answering would put that
// address back. After that, a reload (such as after adding a package) asks for the filtered address.
watch([filters, hasLoaded], () => hasLoaded.value && keepAddressInStep(), {
    deep: true,
});

// A save that was on its way while the filters changed (quick add) answers with the address it was sent
// from, so the address is put back in step with the filters once it arrives. Only for answers that stay on
// this list: leaving for another page also ends in "success", and its address must stay.
const stopListening = router.on('success', (event) => {
    const answer = event.detail.page;
    const staysOnThisList =
        answer.component === page.component &&
        (answer.props.project as ProjectHeader | undefined)?.id ===
            props.project.id;

    if (staysOnThisList && hasLoaded.value) {
        keepAddressInStep();
    }
});
onUnmounted(stopListening);

const cannotAddWithoutCategory = computed(
    () => props.categoryRequired && props.categories.length === 0,
);

/**
 * Shows the filters in the page address without asking the server. It replaces the current history
 * entry instead of adding one, so Back leaves the list rather than undoing filters one by one.
 */
function keepAddressInStep(): void {
    const url = PackageController.index.url(props.project.id, {
        query: filtersToQuery(filters.value),
    });

    if (url !== page.url) {
        router.replace({ url, preserveState: true, preserveScroll: true });
    }
}

function toggleFolded(row: PackageRow): void {
    const next = new Set(folded.value);

    if (next.has(row.id)) {
        next.delete(row.id);
    } else {
        next.add(row.id);
    }

    folded.value = next;
}

function foldAll(): void {
    folded.value = new Set(parents.value);
}

function unfoldAll(): void {
    folded.value = new Set();
}

function startAddingUnder(row: PackageRow): void {
    if (folded.value.has(row.id)) {
        toggleFolded(row);
    }

    addingUnderId.value = row.id;
}

function clearFilters(): void {
    searchInput.value = '';
    filters.value = { ...NO_FILTERS };
}
</script>

<template>
    <Head :title="$t('Packages')" />

    <ProjectLayout :project="project">
        <template v-if="can.create">
            <div
                v-if="cannotAddWithoutCategory"
                class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
            >
                {{
                    $t(
                        'Category codes are part of this project’s package numbers, so packages can be added once the project has categories.',
                    )
                }}
                <Link
                    v-if="project.can.update"
                    :href="ProjectNumberingController.edit(project.id)"
                    class="font-medium text-foreground underline underline-offset-4"
                >
                    {{ $t('Add categories') }}
                </Link>
                <template v-else>
                    {{ $t('Ask a project manager to add them.') }}
                </template>
            </div>
            <QuickAddPackage
                v-else
                :project-id="project.id"
                :categories="categories"
                :category-required="categoryRequired"
                :reload-only="RELOAD_AFTER_ADDING"
            />
        </template>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative max-w-sm min-w-48 flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchInput"
                    type="search"
                    :placeholder="$t('Search by number, title or person')"
                    :aria-label="$t('Search packages')"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.status"
                :aria-label="$t('Filter by status')"
                class="w-36"
            >
                <option value="">{{ $t('All statuses') }}</option>
                <option
                    v-for="status in statuses"
                    :key="status.value"
                    :value="status.value"
                >
                    {{ status.label }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-if="categories.length > 0"
                v-model="filters.category"
                :aria-label="$t('Filter by category')"
                class="w-48"
            >
                <option value="">{{ $t('All categories') }}</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{
                        $t(':code – :name', {
                            code: category.code,
                            name: category.name,
                        })
                    }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-model="filters.risk"
                :aria-label="$t('Filter by risk')"
                class="w-48"
            >
                <option value="">{{ $t('All risk levels') }}</option>
                <option value="atRisk">
                    {{ $t('At risk (amber or red)') }}
                </option>
                <option value="amber">{{ $t('Amber only') }}</option>
                <option value="red">{{ $t('Red only') }}</option>
            </NativeSelect>
            <NativeSelect
                v-model="attentionFilter"
                :aria-label="$t('Filter by what needs attention')"
                class="w-52"
            >
                <option value="">{{ $t('All reasons and dates') }}</option>
                <option value="overdue">{{ $t('Overdue') }}</option>
                <option value="due_soon">
                    {{ $t('Due within :days days', { days: DUE_SOON_DAYS }) }}
                </option>
                <option value="people">
                    {{ $t('Missing owner or buyer') }}
                </option>
                <option value="no_need_date">{{ $t('No need date') }}</option>
                <option value="manual_flag">{{ $t('Flagged by hand') }}</option>
            </NativeSelect>
            <Button
                v-if="isFiltering"
                variant="ghost"
                size="sm"
                @click="clearFilters"
            >
                {{ $t('Clear filters') }}
            </Button>
            <div v-else-if="parents.size > 0" class="ml-auto flex gap-1">
                <Button variant="ghost" size="sm" @click="unfoldAll">
                    {{ $t('Unfold all') }}
                </Button>
                <Button variant="ghost" size="sm" @click="foldAll">
                    {{ $t('Fold all') }}
                </Button>
            </div>
        </div>

        <p
            v-if="isFiltering && hasLoaded"
            class="text-sm text-muted-foreground"
            aria-live="polite"
        >
            {{
                $t(':count of :total packages match', {
                    count: matchingIds?.size ?? 0,
                    total: allPackages.length,
                })
            }}
        </p>

        <Deferred data="packages">
            <template #fallback>
                <PackageTreeTableSkeleton />
            </template>
            <PackageTreeTable
                ref="table"
                :project-id="project.id"
                :packages="allPackages"
                :rows="rows"
                :matching-ids="matchingIds"
                :parents="parents"
                :folded="folded"
                :purchase-type-labels="purchaseTypeLabels"
                :categories="categories"
                :can-create="can.create"
                :adding-under-id="addingUnderId"
                :reload-only="RELOAD_AFTER_ADDING"
                @toggle-folded="toggleFolded"
                @add-under="startAddingUnder"
                @close-adding="addingUnderId = null"
            >
                <template #empty>
                    {{
                        isFiltering
                            ? $t('No packages match.')
                            : $t('No packages yet.')
                    }}
                </template>
            </PackageTreeTable>
        </Deferred>
    </ProjectLayout>
</template>
