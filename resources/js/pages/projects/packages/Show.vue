<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Check, ChevronRight, Pencil } from '@lucide/vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import PackageScheduleController from '@/actions/App/Http/Controllers/Projects/PackageScheduleController';
import Heading from '@/components/Heading.vue';
import PackageStatusBadge from '@/components/packages/PackageStatusBadge.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { floatInWords } from '@/lib/scheduleFloat';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    PackageDetails,
    PackageLink,
    PackageScheduleSummary,
    ProjectCategory,
    ProjectHeader,
    SubPackageRow,
} from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader; package: PackageDetails }) => {
        const { breadcrumbs } = projectBreadcrumbs(props.project, {
            title: trans('Packages'),
            href: PackageController.index(props.project.id),
        });

        return {
            breadcrumbs: [
                ...breadcrumbs,
                {
                    title: props.package.number,
                    href: PackageController.show([
                        props.project.id,
                        props.package.id,
                    ]),
                },
            ],
        };
    },
});

defineProps<{
    project: ProjectHeader;
    package: PackageDetails;
    ancestors: PackageLink[];
    schedule: PackageScheduleSummary;
    subPackages: SubPackageRow[];
    categories: ProjectCategory[];
    can: { update: boolean; create: boolean };
}>();

function dateOrDash(date: string | null): string {
    return date === null ? '–' : formatDate(date);
}

function floatOrDash(days: number | null): string {
    return days === null ? '–' : floatInWords(days);
}
</script>

<template>
    <Head :title="`${package.number} ${package.title}`" />

    <ProjectLayout :project="project">
        <nav
            v-if="ancestors.length > 0"
            :aria-label="$t('Packages above this one')"
            class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
        >
            <template v-for="ancestor in ancestors" :key="ancestor.id">
                <Link
                    :href="PackageController.show([project.id, ancestor.id])"
                    class="hover:text-foreground hover:underline"
                >
                    <span class="font-mono">{{ ancestor.number }}</span>
                    {{ ancestor.title }}
                </Link>
                <ChevronRight class="size-4" />
            </template>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <p class="font-mono text-sm text-muted-foreground">
                    {{ package.number }}
                </p>
                <Heading variant="small" :title="package.title" class="mb-0" />
            </div>
            <Button v-if="can.update" as-child>
                <Link :href="PackageController.edit([project.id, package.id])">
                    <Pencil />
                    {{ $t('Edit package') }}
                </Link>
            </Button>
        </div>

        <dl
            class="grid max-w-3xl grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-[auto_1fr]"
        >
            <dt class="text-muted-foreground">{{ $t('Status') }}</dt>
            <dd><PackageStatusBadge :status="package.status" /></dd>

            <dt class="text-muted-foreground">{{ $t('Category') }}</dt>
            <dd>
                {{
                    package.category
                        ? $t(':code – :name', {
                              code: package.category.code,
                              name: package.category.name,
                          })
                        : '–'
                }}
            </dd>

            <dt class="text-muted-foreground">{{ $t('Purchase type') }}</dt>
            <dd>{{ package.purchaseType ?? '–' }}</dd>

            <dt class="text-muted-foreground">{{ $t('Quantity') }}</dt>
            <dd>
                {{
                    package.quantity
                        ? `${Number(package.quantity)} ${package.unit ?? ''}`
                        : '–'
                }}
            </dd>

            <dt class="text-muted-foreground">{{ $t('Package owner') }}</dt>
            <dd>{{ package.ownerName ?? '–' }}</dd>

            <dt class="text-muted-foreground">
                {{ $t('Responsible buyer') }}
            </dt>
            <dd>{{ package.buyerName ?? '–' }}</dd>

            <dt class="text-muted-foreground">{{ $t('Description') }}</dt>
            <dd class="whitespace-pre-line">
                {{ package.description ?? '–' }}
            </dd>

            <dt class="text-muted-foreground">{{ $t('Notes') }}</dt>
            <dd class="whitespace-pre-line">{{ package.notes ?? '–' }}</dd>
        </dl>

        <section class="space-y-4" :aria-label="$t('Milestones')">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <Heading
                        variant="small"
                        :title="$t('Milestones')"
                        class="mb-0"
                    />
                    <p
                        class="text-sm text-muted-foreground"
                        data-test="schedule-summary"
                    >
                        {{
                            $t(
                                'Need date: :need · Expected: :expected · :float',
                                {
                                    need: dateOrDash(schedule.needDate),
                                    expected: dateOrDash(
                                        schedule.expectedNeedDate,
                                    ),
                                    float: floatOrDash(schedule.floatDays),
                                },
                            )
                        }}
                    </p>
                    <p
                        v-if="schedule.withSubPackages"
                        class="text-sm text-muted-foreground"
                        data-test="schedule-summary-with-sub-packages"
                    >
                        {{
                            $t(
                                'With sub-packages: expected :expected · :float',
                                {
                                    expected: dateOrDash(
                                        schedule.withSubPackages
                                            .expectedNeedDate,
                                    ),
                                    float: floatOrDash(
                                        schedule.withSubPackages.floatDays,
                                    ),
                                },
                            )
                        }}
                    </p>
                </div>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link
                        :href="
                            PackageScheduleController.edit([
                                project.id,
                                package.id,
                            ])
                        "
                    >
                        <CalendarDays />
                        {{ $t('Edit dates') }}
                    </Link>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Milestone') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Planned') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Forecast') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Actual') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ $t('Lead time (days)') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Expected') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Latest') }}
                            </th>
                            <th
                                v-if="schedule.withSubPackages"
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ $t('Expected with sub-packages') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="milestone in schedule.milestones"
                            :key="milestone.id"
                        >
                            <th
                                scope="row"
                                class="px-4 py-2 text-left font-medium"
                            >
                                <span class="flex flex-wrap items-center gap-2">
                                    <Check
                                        v-if="milestone.isDone"
                                        class="size-4 text-green-600 dark:text-green-400"
                                        :aria-label="$t('Done')"
                                        role="img"
                                    />
                                    {{ milestone.name }}
                                    <Badge
                                        v-if="milestone.isNeedDate"
                                        variant="secondary"
                                    >
                                        {{ $t('Need date') }}
                                    </Badge>
                                </span>
                            </th>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(milestone.planned) }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(milestone.forecast) }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(milestone.actual) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ milestone.leadDays ?? '–' }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(milestone.expected) }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ dateOrDash(milestone.latest) }}
                            </td>
                            <td
                                v-if="schedule.withSubPackages"
                                class="px-4 py-2 whitespace-nowrap"
                            >
                                {{
                                    dateOrDash(
                                        milestone.expectedWithSubPackages,
                                    )
                                }}
                            </td>
                        </tr>
                        <tr v-if="schedule.milestones.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                {{ $t('This project has no milestones yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <Heading variant="small" :title="$t('Sub-packages')" class="mb-0" />

            <QuickAddPackage
                v-if="can.create"
                :project-id="project.id"
                :categories="categories"
                :parent="{
                    id: package.id,
                    number: package.number,
                    categoryId: package.category?.id ?? null,
                }"
            />

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
                                {{ $t('Category') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="child in subPackages" :key="child.id">
                            <td class="px-4 py-2 font-mono whitespace-nowrap">
                                {{ child.number }}
                            </td>
                            <td class="px-4 py-2">
                                <Link
                                    :href="
                                        PackageController.show([
                                            project.id,
                                            child.id,
                                        ])
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ child.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-2">{{ child.categoryCode }}</td>
                            <td class="px-4 py-2">
                                <PackageStatusBadge :status="child.status" />
                            </td>
                        </tr>
                        <tr v-if="subPackages.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                {{ $t('No sub-packages.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </ProjectLayout>
</template>
