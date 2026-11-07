<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Pencil, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import PackageStatusBadge from '@/components/packages/PackageStatusBadge.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { packagesWithChildren, visiblePackageRows } from '@/lib/packageTree';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import { floatInWords, floatShort } from '@/lib/scheduleFloat';
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

const search = ref('');
const statusFilter = ref<PackageStatus | ''>('');
const categoryFilter = ref<number | ''>('');
const folded = ref(new Set<number>());
const addingUnder = ref<PackageRow | null>(null);

const parents = computed(() => packagesWithChildren(props.packages));

const purchaseTypeLabels = computed(
    () => new Map(props.purchaseTypes.map((type) => [type.value, type.label])),
);

const isFiltering = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== '' ||
        categoryFilter.value !== '',
);

const rows = computed(() =>
    visiblePackageRows(
        props.packages,
        folded.value,
        isFiltering.value ? rowMatchesFilters : null,
    ),
);

// Packages above a match are shown only to place it in the tree, so they are dimmed.
const matchingIds = computed(() =>
    isFiltering.value
        ? new Set(rows.value.filter(rowMatchesFilters).map((row) => row.id))
        : null,
);

const cannotAddWithoutCategory = computed(
    () => props.categoryRequired && props.categories.length === 0,
);

function rowMatchesFilters(row: PackageRow): boolean {
    const term = search.value.trim().toLocaleLowerCase();

    return (
        (term === '' ||
            [row.number, row.title, row.ownerName, row.buyerName].some((text) =>
                text?.toLocaleLowerCase().includes(term),
            )) &&
        (statusFilter.value === '' || row.status === statusFilter.value) &&
        (categoryFilter.value === '' || row.categoryId === categoryFilter.value)
    );
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

    addingUnder.value = row;
}

function dateOrDash(date: string | null): string {
    return date === null ? '–' : formatDate(date);
}

function clearFilters(): void {
    search.value = '';
    statusFilter.value = '';
    categoryFilter.value = '';
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
                    v-model="search"
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

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Number') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Title') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Status') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Next milestone') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Need date') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Expected') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-right font-medium"
                        >
                            {{ $t('Float') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Type') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Owner') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Buyer') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">{{ $t('Actions') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <template v-for="row in rows" :key="row.id">
                        <tr
                            :class="
                                matchingIds && !matchingIds.has(row.id)
                                    ? 'text-muted-foreground'
                                    : undefined
                            "
                        >
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div
                                    class="flex items-center gap-1"
                                    :style="{
                                        paddingLeft: `${row.depth * 1.25}rem`,
                                    }"
                                >
                                    <Button
                                        v-if="
                                            parents.has(row.id) && !isFiltering
                                        "
                                        variant="ghost"
                                        size="icon"
                                        class="size-6"
                                        :aria-expanded="!folded.has(row.id)"
                                        :aria-label="
                                            folded.has(row.id)
                                                ? $t(
                                                      'Show sub-packages of :number',
                                                      {
                                                          number: row.number,
                                                      },
                                                  )
                                                : $t(
                                                      'Hide sub-packages of :number',
                                                      {
                                                          number: row.number,
                                                      },
                                                  )
                                        "
                                        @click="toggleFolded(row)"
                                    >
                                        <ChevronRight
                                            v-if="folded.has(row.id)"
                                        />
                                        <ChevronDown v-else />
                                    </Button>
                                    <span v-else class="size-6" />
                                    <span class="font-mono">{{
                                        row.number
                                    }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <Link
                                    :href="
                                        PackageController.show([
                                            project.id,
                                            row.id,
                                        ])
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ row.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-2">
                                <PackageStatusBadge :status="row.status" />
                            </td>
                            <td class="min-w-40 px-4 py-2">
                                <template v-if="row.nextMilestoneName">
                                    <div>{{ row.nextMilestoneName }}</div>
                                    <div
                                        v-if="row.nextMilestoneDate"
                                        class="text-xs whitespace-nowrap text-muted-foreground"
                                    >
                                        {{ formatDate(row.nextMilestoneDate) }}
                                    </div>
                                </template>
                                <template v-else>–</template>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(row.needDate) }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(row.expectedNeedDate) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                                :class="{
                                    'text-destructive':
                                        row.floatDays !== null &&
                                        row.floatDays < 0,
                                }"
                            >
                                <template v-if="row.floatDays === null">
                                    –
                                </template>
                                <span
                                    v-else
                                    :title="floatInWords(row.floatDays)"
                                >
                                    <span aria-hidden="true">{{
                                        floatShort(row.floatDays)
                                    }}</span>
                                    <span class="sr-only">{{
                                        floatInWords(row.floatDays)
                                    }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                {{
                                    row.purchaseType
                                        ? purchaseTypeLabels.get(
                                              row.purchaseType,
                                          )
                                        : ''
                                }}
                            </td>
                            <td class="px-4 py-2">{{ row.ownerName }}</td>
                            <td class="px-4 py-2">{{ row.buyerName }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <Button
                                    v-if="can.create"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="
                                        $t('Add a sub-package under :number', {
                                            number: row.number,
                                        })
                                    "
                                    @click="startAddingUnder(row)"
                                >
                                    <Plus />
                                </Button>
                                <Button
                                    v-if="row.canEdit"
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            PackageController.edit([
                                                project.id,
                                                row.id,
                                            ])
                                        "
                                        :aria-label="
                                            $t('Edit :number', {
                                                number: row.number,
                                            })
                                        "
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="addingUnder?.id === row.id">
                            <td colspan="11" class="bg-muted/30 px-4 py-3">
                                <div
                                    :style="{
                                        paddingLeft: `${(row.depth + 1) * 1.25}rem`,
                                    }"
                                >
                                    <QuickAddPackage
                                        :key="row.id"
                                        :project-id="project.id"
                                        :categories="categories"
                                        :parent="row"
                                        autofocus
                                        closable
                                        @close="addingUnder = null"
                                    />
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="rows.length === 0">
                        <td
                            colspan="11"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            {{
                                isFiltering
                                    ? $t('No packages match.')
                                    : $t('No packages yet.')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ProjectLayout>
</template>
