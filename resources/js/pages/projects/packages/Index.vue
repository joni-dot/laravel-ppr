<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import PackageTreeTable from '@/components/packages/PackageTreeTable.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { trans } from '@/lib/i18n';
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
    packages: PackageRow[];
    categories: ProjectCategory[];
    categoryRequired: boolean;
    statuses: Option<PackageStatus>[];
    purchaseTypes: Option<PurchaseType>[];
    can: { create: boolean };
}>();

// Searching waits for a short pause in typing, so the list isn't filtered again for every letter.
const SEARCH_DELAY_MS = 150;

const searchInput = ref('');
const search = ref('');
const statusFilter = ref<PackageStatus | ''>('');
const categoryFilter = ref<number | ''>('');
// "atRisk" is amber or red.
const riskFilter = ref<'' | 'atRisk' | 'red'>('');
const folded = ref(new Set<number>());
const addingUnderId = ref<number | null>(null);
const table = useTemplateRef<InstanceType<typeof PackageTreeTable>>('table');

watchDebounced(
    searchInput,
    (value) => {
        search.value = value;
    },
    { debounce: SEARCH_DELAY_MS },
);

const parents = computed(() => packagesWithChildren(props.packages));

const purchaseTypeLabels = computed(
    () => new Map(props.purchaseTypes.map((type) => [type.value, type.label])),
);

const searchTerm = computed(() => search.value.trim().toLocaleLowerCase());

const isFiltering = computed(
    () =>
        searchTerm.value !== '' ||
        statusFilter.value !== '' ||
        categoryFilter.value !== '' ||
        riskFilter.value !== '',
);

const matchingIds = computed(() =>
    isFiltering.value
        ? new Set(props.packages.filter(rowMatchesFilters).map((row) => row.id))
        : null,
);

const rows = computed(() =>
    visiblePackageRows(props.packages, folded.value, matchingIds.value),
);

watch([search, statusFilter, categoryFilter, riskFilter], () =>
    table.value?.scrollToStart(),
);

const cannotAddWithoutCategory = computed(
    () => props.categoryRequired && props.categories.length === 0,
);

// Each row's searchable text, lowercased once instead of on every search.
const searchTexts = new WeakMap<PackageRow, string>();

function searchTextOf(row: PackageRow): string {
    let text = searchTexts.get(row);

    if (text === undefined) {
        text = [row.number, row.title, row.ownerName, row.buyerName]
            .filter((part) => part !== null)
            .join('\n')
            .toLocaleLowerCase();
        searchTexts.set(row, text);
    }

    return text;
}

function rowMatchesFilters(row: PackageRow): boolean {
    return (
        (searchTerm.value === '' ||
            searchTextOf(row).includes(searchTerm.value)) &&
        (statusFilter.value === '' || row.status === statusFilter.value) &&
        (categoryFilter.value === '' ||
            row.categoryId === categoryFilter.value) &&
        rowMatchesRiskFilter(row)
    );
}

function rowMatchesRiskFilter(row: PackageRow): boolean {
    switch (riskFilter.value) {
        case '':
            return true;
        case 'atRisk':
            return row.risk !== null;
        case 'red':
            return row.risk?.level === 'red';
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
    search.value = '';
    statusFilter.value = '';
    categoryFilter.value = '';
    riskFilter.value = '';
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
            />
        </template>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-sm">
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
                v-model="statusFilter"
                :aria-label="$t('Filter by status')"
                class="w-44"
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
                v-model="categoryFilter"
                :aria-label="$t('Filter by category')"
                class="w-56"
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
                v-model="riskFilter"
                :aria-label="$t('Filter by risk')"
                class="w-56"
            >
                <option value="">{{ $t('All risk levels') }}</option>
                <option value="atRisk">
                    {{ $t('At risk (amber or red)') }}
                </option>
                <option value="red">{{ $t('Red only') }}</option>
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
            v-if="isFiltering"
            class="text-sm text-muted-foreground"
            aria-live="polite"
        >
            {{
                $t(':count of :total packages match', {
                    count: matchingIds?.size ?? 0,
                    total: packages.length,
                })
            }}
        </p>

        <PackageTreeTable
            ref="table"
            :project-id="project.id"
            :packages="packages"
            :rows="rows"
            :matching-ids="matchingIds"
            :parents="parents"
            :folded="folded"
            :purchase-type-labels="purchaseTypeLabels"
            :categories="categories"
            :can-create="can.create"
            :adding-under-id="addingUnderId"
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
    </ProjectLayout>
</template>
