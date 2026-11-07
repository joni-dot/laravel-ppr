<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProjectMemberController from '@/actions/App/Http/Controllers/Projects/ProjectMemberController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type { ProjectHeader } from '@/types';

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
