<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import CreateProjectDialog from '@/components/projects/CreateProjectDialog.vue';
import { Input } from '@/components/ui/input';
import { trans } from '@/lib/i18n';
import type { Paginated, PersonOption, ProjectListItem } from '@/types';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: trans('Projects'), href: ProjectController.index() },
        ],
    }),
});

const props = defineProps<{
    projects: Paginated<ProjectListItem>;
    filters: { search: string };
    can: { create: boolean };
    managerOptions?: PersonOption[];
}>();

const search = ref(props.filters.search);

const searchProjects = useDebounceFn((term: string) => {
    router.get(
        ProjectController.index.url(),
        { search: term || undefined },
        {
            only: ['projects', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}, 300);

watch(search, searchProjects);
</script>

<template>
    <Head :title="$t('Projects')" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Projects')"
                :description="$t('The projects you are a member of')"
                class="mb-0"
            />
            <CreateProjectDialog
                v-if="can.create"
                :manager-options="managerOptions"
            />
        </div>

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Search by name or code')"
                :aria-label="$t('Search projects')"
                class="pl-9"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Code') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Name') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Your role') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-right font-medium"
                        >
                            {{ $t('Members') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="project in projects.data" :key="project.id">
                        <td class="px-4 py-3 font-mono">{{ project.code }}</td>
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="ProjectController.show(project.id)"
                                class="hover:underline"
                            >
                                {{ project.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            {{ project.myRole ?? $t('Admin') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ project.membersCount }}
                        </td>
                    </tr>
                    <tr v-if="projects.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            {{
                                filters.search
                                    ? $t('No projects found.')
                                    : $t(
                                          'You are not a member of any project yet.',
                                      )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="projects" />
    </div>
</template>
