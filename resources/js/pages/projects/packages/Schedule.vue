<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import PackageScheduleController from '@/actions/App/Http/Controllers/Projects/PackageScheduleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { trans } from '@/lib/i18n';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type { EditableMilestone, PackageLink, ProjectHeader } from '@/types';

type DateField = 'planned_date' | 'forecast_date' | 'actual_date';

defineOptions({
    layout: (props: { project: ProjectHeader; package: PackageLink }) => {
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
                {
                    title: trans('Edit dates'),
                    href: PackageScheduleController.edit([
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
    package: PackageLink;
    milestones: EditableMilestone[];
    canChangePlannedDates: boolean;
    today: string;
}>();

const form = useForm({
    milestones: props.milestones.map((milestone) => ({
        id: milestone.id,
        planned_date: milestone.plannedDate ?? '',
        forecast_date: milestone.forecastDate ?? '',
        actual_date: milestone.actualDate ?? '',
        lead_days: milestone.leadDays ?? ('' as number | ''),
    })),
});

const hasLockedPlannedDates =
    !props.canChangePlannedDates &&
    props.milestones.some((milestone) => milestone.plannedDate !== null);

function isPlannedDateLocked(milestone: EditableMilestone): boolean {
    return !props.canChangePlannedDates && milestone.plannedDate !== null;
}

function errorFor(
    index: number,
    field: DateField | 'id' | 'lead_days',
): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `milestones.${index}.${field}`
    ];
}

function save(): void {
    form.put(
        PackageScheduleController.update.url([
            props.project.id,
            props.package.id,
        ]),
    );
}
</script>

<template>
    <Head :title="$t('Dates of :number', { number: package.number })" />

    <ProjectLayout :project="project">
        <form class="space-y-6" @submit.prevent="save">
            <Heading
                variant="small"
                :title="$t('Dates of :number', { number: package.number })"
                :description="
                    $t(
                        'Lead time is the number of calendar days from the previous milestone to this one. The package page works out the expected and latest dates from these.',
                    )
                "
            />

            <p
                v-if="hasLockedPlannedDates"
                id="locked-planned-dates"
                class="text-sm text-muted-foreground"
            >
                {{
                    $t(
                        'Only a project manager can change a planned date once it is set.',
                    )
                }}
            </p>

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
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Lead time (days)') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(milestone, index) in milestones"
                            :key="milestone.id"
                            class="align-top"
                        >
                            <th
                                scope="row"
                                class="px-4 py-3 text-left font-medium"
                            >
                                <span class="flex flex-wrap items-center gap-2">
                                    {{ milestone.name }}
                                    <Badge
                                        v-if="milestone.isNeedDate"
                                        variant="secondary"
                                    >
                                        {{ $t('Need date') }}
                                    </Badge>
                                </span>
                                <InputError :message="errorFor(index, 'id')" />
                            </th>
                            <td class="space-y-1 px-4 py-2">
                                <Input
                                    v-model="
                                        form.milestones[index].planned_date
                                    "
                                    type="date"
                                    min="2000-01-01"
                                    max="2100-12-31"
                                    class="w-40"
                                    :readonly="isPlannedDateLocked(milestone)"
                                    :aria-describedby="
                                        isPlannedDateLocked(milestone)
                                            ? 'locked-planned-dates'
                                            : undefined
                                    "
                                    :aria-label="
                                        $t('Planned date of :milestone', {
                                            milestone: milestone.name,
                                        })
                                    "
                                    :aria-invalid="
                                        errorFor(index, 'planned_date')
                                            ? true
                                            : undefined
                                    "
                                />
                                <InputError
                                    :message="errorFor(index, 'planned_date')"
                                />
                            </td>
                            <td class="space-y-1 px-4 py-2">
                                <Input
                                    v-model="
                                        form.milestones[index].forecast_date
                                    "
                                    type="date"
                                    min="2000-01-01"
                                    max="2100-12-31"
                                    class="w-40"
                                    :aria-label="
                                        $t('Forecast date of :milestone', {
                                            milestone: milestone.name,
                                        })
                                    "
                                    :aria-invalid="
                                        errorFor(index, 'forecast_date')
                                            ? true
                                            : undefined
                                    "
                                />
                                <InputError
                                    :message="errorFor(index, 'forecast_date')"
                                />
                            </td>
                            <td class="space-y-1 px-4 py-2">
                                <Input
                                    v-model="form.milestones[index].actual_date"
                                    type="date"
                                    min="2000-01-01"
                                    :max="today"
                                    class="w-40"
                                    :aria-label="
                                        $t('Actual date of :milestone', {
                                            milestone: milestone.name,
                                        })
                                    "
                                    :aria-invalid="
                                        errorFor(index, 'actual_date')
                                            ? true
                                            : undefined
                                    "
                                />
                                <InputError
                                    :message="errorFor(index, 'actual_date')"
                                />
                            </td>
                            <td class="space-y-1 px-4 py-2">
                                <!-- Lead time counts from the previous milestone, so the first one has none. -->
                                <template v-if="index > 0">
                                    <Input
                                        v-model="
                                            form.milestones[index].lead_days
                                        "
                                        type="number"
                                        min="0"
                                        max="3650"
                                        step="1"
                                        class="w-24"
                                        :aria-label="
                                            $t(
                                                'Lead time in days of :milestone',
                                                {
                                                    milestone: milestone.name,
                                                },
                                            )
                                        "
                                        :aria-invalid="
                                            errorFor(index, 'lead_days')
                                                ? true
                                                : undefined
                                        "
                                    />
                                    <InputError
                                        :message="errorFor(index, 'lead_days')"
                                    />
                                </template>
                            </td>
                        </tr>
                        <tr v-if="milestones.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                {{ $t('This project has no milestones yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ $t('Save dates') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link
                        :href="PackageController.show([project.id, package.id])"
                    >
                        {{ $t('Cancel') }}
                    </Link>
                </Button>
            </div>
        </form>
    </ProjectLayout>
</template>
