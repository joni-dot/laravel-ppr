<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Check, ChevronRight, Flag, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import PackageRiskFlagController from '@/actions/App/Http/Controllers/Projects/PackageRiskFlagController';
import PackageScheduleController from '@/actions/App/Http/Controllers/Projects/PackageScheduleController';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import PackageRiskBadge from '@/components/packages/PackageRiskBadge.vue';
import PackageStatusBadge from '@/components/packages/PackageStatusBadge.vue';
import PackageTimeline from '@/components/packages/PackageTimeline.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { riskReasonInWords } from '@/lib/packageRisk';
import { floatInWords } from '@/lib/scheduleFloat';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    PackageDetails,
    PackageLink,
    PackageRiskOverview,
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

const props = defineProps<{
    project: ProjectHeader;
    package: PackageDetails;
    ancestors: PackageLink[];
    schedule: PackageScheduleSummary;
    risk: PackageRiskOverview;
    subPackages: SubPackageRow[];
    categories: ProjectCategory[];
    can: { update: boolean; create: boolean };
}>();

const flagForm = useForm({ reason: props.risk.flag?.reason ?? '' });

const isClosedOrCancelled = computed(
    () =>
        props.package.status === 'closed' ||
        props.package.status === 'cancelled',
);

const riskReasons = computed(() =>
    props.risk.reasons.map((reason) =>
        riskReasonInWords(reason, {
            floatDays: props.risk.floatDays,
            overdueMilestone: props.risk.overdueMilestone?.name,
            overdueDate: props.risk.overdueMilestone?.expected,
            flagReason: props.risk.flag?.reason,
        }),
    ),
);

const flaggedBy = computed(() => {
    const flag = props.risk.flag;

    if (flag === null || flag.flaggedAt === null) {
        return null;
    }

    return flag.flaggedByName === null
        ? trans('Flagged on :date', { date: formatDateTime(flag.flaggedAt) })
        : trans('Flagged by :name on :date', {
              name: flag.flaggedByName,
              date: formatDateTime(flag.flaggedAt),
          });
});

function saveFlag(): void {
    flagForm.post(
        PackageRiskFlagController.store.url([
            props.project.id,
            props.package.id,
        ]),
        { preserveScroll: true },
    );
}

function clearFlag(): void {
    router.delete(
        PackageRiskFlagController.destroy.url([
            props.project.id,
            props.package.id,
        ]),
        {
            preserveScroll: true,
            onSuccess: () => {
                flagForm.reason = '';
                flagForm.clearErrors();
            },
        },
    );
}

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

        <section
            class="max-w-3xl space-y-4"
            :aria-label="$t('Risk')"
            data-test="risk-panel"
        >
            <div class="flex flex-wrap items-center gap-3">
                <Heading variant="small" :title="$t('Risk')" class="mb-0" />
                <PackageRiskBadge
                    v-if="risk.level !== 'none'"
                    :level="risk.level"
                />
            </div>

            <p v-if="isClosedOrCancelled" class="text-sm text-muted-foreground">
                {{ $t('Closed and cancelled packages are never at risk.') }}
            </p>
            <p
                v-else-if="risk.reasons.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing puts this package at risk right now.') }}
            </p>
            <ul v-else class="list-disc space-y-1 pl-5 text-sm">
                <li v-for="(reason, index) in riskReasons" :key="index">
                    {{ reason }}
                </li>
            </ul>
            <p
                v-if="flaggedBy"
                class="text-sm text-muted-foreground"
                data-test="risk-flagged-by"
            >
                {{ flaggedBy }}
            </p>

            <form
                v-if="can.update"
                class="space-y-3"
                @submit.prevent="saveFlag"
            >
                <FormField
                    id="risk_flag_reason"
                    :label="$t('Reason for the high-risk flag')"
                    :hint="
                        risk.flag
                            ? undefined
                            : $t(
                                  'Turns the package red, for example when a supplier is in trouble. Say why.',
                              )
                    "
                    :error="flagForm.errors.reason"
                >
                    <Textarea
                        id="risk_flag_reason"
                        v-model="flagForm.reason"
                        maxlength="500"
                        rows="2"
                        required
                    />
                </FormField>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="flagForm.processing"
                    >
                        <Spinner v-if="flagForm.processing" />
                        <Flag v-else />
                        {{
                            risk.flag
                                ? $t('Save reason')
                                : $t('Flag as high risk')
                        }}
                    </Button>
                    <Button
                        v-if="risk.flag"
                        type="button"
                        variant="ghost"
                        @click="clearFlag"
                    >
                        {{ $t('Clear flag') }}
                    </Button>
                </div>
            </form>
        </section>

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

            <PackageTimeline :schedule="schedule" :risk-level="risk.level" />

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
