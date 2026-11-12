<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ProjectTiles from '@/components/dashboard/ProjectTiles.vue';
import { show as showProject } from '@/routes/projects';
import type { DashboardProject, DashboardTiles } from '@/types';

defineProps<{
    projects: DashboardProject[];
    // Missing while the numbers load.
    tilesByProject?: Map<number, DashboardTiles>;
}>();
</script>

<template>
    <ul class="divide-y rounded-xl border" data-test="my-projects">
        <li
            v-for="project in projects"
            :key="project.id"
            class="flex flex-wrap items-center justify-between gap-x-8 gap-y-3 px-4 py-3"
            data-test="dashboard-project"
            :data-project-code="project.code"
        >
            <div class="min-w-0">
                <Link
                    :href="showProject(project.id)"
                    class="font-medium hover:underline"
                >
                    {{ project.name }}
                </Link>
                <p class="text-sm text-muted-foreground">
                    {{ project.code }} · {{ project.role }}
                </p>
            </div>

            <ProjectTiles
                :project-id="project.id"
                :tiles="tilesByProject?.get(project.id)"
            />
        </li>
    </ul>
</template>
