<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import ProjectMemberController from '@/actions/App/Http/Controllers/Projects/ProjectMemberController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import AttentionSkeleton from '@/components/attention/AttentionSkeleton.vue';
import AttentionTiles from '@/components/attention/AttentionTiles.vue';
import MilestonesPerWeekChart from '@/components/attention/MilestonesPerWeekChart.vue';
import RiskByCategoryChart from '@/components/attention/RiskByCategoryChart.vue';
import StatusCounts from '@/components/attention/StatusCounts.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type { ProjectAttentionOverview, ProjectHeader } from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader }) =>
        projectBreadcrumbs(props.project),
});

defineProps<{
    project: ProjectHeader;
    description: string | null;
    team: { role: string; count: number }[];
    risk: { amberDays: number; redDays: number };
    exampleNumbers: { package: string; subPackage: string };
    // Deferred: missing while the page first loads; see ProjectController::show().
    attention?: ProjectAttentionOverview;
}>();
</script>

<template>
    <Head :title="project.name" />

    <ProjectLayout :project="project">
        <p
            v-if="description"
            class="max-w-3xl text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ description }}
        </p>

        <section
            class="grid gap-4"
            aria-labelledby="attention-heading"
            data-test="project-attention"
        >
            <h2 id="attention-heading" class="sr-only">
                {{ $t('What needs attention') }}
            </h2>
            <Deferred data="attention">
                <template #fallback>
                    <AttentionSkeleton />
                </template>
                <template v-if="attention">
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t('Open packages: :open of :total', {
                                open: attention.tiles.open,
                                total: attention.tiles.total,
                            })
                        }}
                    </p>
                    <AttentionTiles
                        :project-id="project.id"
                        :tiles="attention.tiles"
                        :due-soon-days="attention.dueSoonDays"
                    />
                    <StatusCounts
                        :project-id="project.id"
                        :counts="attention.byStatus"
                    />
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {{ $t('Open packages by category') }}
                                </CardTitle>
                                <CardDescription>
                                    {{ $t('Split by risk level') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <RiskByCategoryChart
                                    :project-id="project.id"
                                    :categories="attention.riskByCategory"
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {{ $t('Milestones due per week') }}
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        $t(
                                            'Milestones not done yet, by the Monday of the week they are expected',
                                        )
                                    }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <MilestonesPerWeekChart
                                    :weeks="attention.milestonesDuePerWeek"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </template>
            </Deferred>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('Team') }}</CardTitle>
                    <CardDescription>
                        <Link
                            :href="ProjectMemberController.index(project.id)"
                            class="hover:underline"
                        >
                            {{ $t('See all members') }}
                        </Link>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <dl
                        class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm"
                    >
                        <template v-for="row in team" :key="row.role">
                            <dt class="text-muted-foreground">
                                {{ row.role }}
                            </dt>
                            <dd class="text-right tabular-nums">
                                {{ row.count }}
                            </dd>
                        </template>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('Package numbers') }}</CardTitle>
                    <CardDescription>
                        <Link
                            v-if="project.can.update"
                            :href="ProjectNumberingController.edit(project.id)"
                            class="hover:underline"
                        >
                            {{ $t('Change numbering') }}
                        </Link>
                        <template v-else>
                            {{ $t('Examples') }}
                        </template>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-y-1 text-sm">
                        <dt class="text-muted-foreground">
                            {{ $t('Next package') }}
                        </dt>
                        <dd
                            class="font-mono"
                            data-test="example-package-number"
                        >
                            {{ exampleNumbers.package }}
                        </dd>
                        <dt class="mt-2 text-muted-foreground">
                            {{ $t('Its first sub-package') }}
                        </dt>
                        <dd
                            class="font-mono"
                            data-test="example-sub-package-number"
                        >
                            {{ exampleNumbers.subPackage }}
                        </dd>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('Risk warnings') }}</CardTitle>
                    <CardDescription>
                        {{
                            $t(
                                'Based on days of spare time before a package is needed',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <dl
                        class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm"
                    >
                        <dt class="text-muted-foreground">
                            {{ $t('Amber below (days)') }}
                        </dt>
                        <dd class="text-right tabular-nums">
                            {{ risk.amberDays }}
                        </dd>
                        <dt class="text-muted-foreground">
                            {{ $t('Red below (days)') }}
                        </dt>
                        <dd class="text-right tabular-nums">
                            {{ risk.redDays }}
                        </dd>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </ProjectLayout>
</template>
