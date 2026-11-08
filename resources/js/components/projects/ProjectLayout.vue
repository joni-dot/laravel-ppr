<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import ProjectMemberController from '@/actions/App/Http/Controllers/Projects/ProjectMemberController';
import ProjectMilestoneController from '@/actions/App/Http/Controllers/Projects/ProjectMilestoneController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import Heading from '@/components/Heading.vue';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { trans } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem, ProjectHeader } from '@/types';

const props = defineProps<{
    project: ProjectHeader;
}>();

const { isCurrentUrl, isCurrentOrParentUrl, isOpeningSlowly } = useCurrentUrl();

type Tab = NavItem & {
    // Stays highlighted on the pages below it, such as a single package.
    includesSubpages?: boolean;
};

const tabs = computed<Tab[]>(() => {
    const { id, can } = props.project;

    const items: { isVisible: boolean; item: Tab }[] = [
        {
            isVisible: true,
            item: {
                title: trans('Overview'),
                href: ProjectController.show(id),
            },
        },
        {
            isVisible: true,
            item: {
                title: trans('Packages'),
                href: PackageController.index(id),
                includesSubpages: true,
            },
        },
        {
            isVisible: true,
            item: {
                title: trans('Members'),
                href: ProjectMemberController.index(id),
            },
        },
        {
            isVisible: can.update,
            item: {
                title: trans('Milestones'),
                href: ProjectMilestoneController.index(id),
            },
        },
        {
            isVisible: can.update,
            item: {
                title: trans('Numbering'),
                href: ProjectNumberingController.edit(id),
            },
        },
        {
            isVisible: can.update,
            item: {
                title: trans('Project settings'),
                href: ProjectController.edit(id),
            },
        },
    ];

    return items.filter(({ isVisible }) => isVisible).map(({ item }) => item);
});

function isCurrentTab(tab: Tab): boolean {
    return tab.includesSubpages
        ? isCurrentOrParentUrl(tab.href)
        : isCurrentUrl(tab.href);
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            :title="project.name"
            :description="project.code"
            class="mb-0"
        />

        <nav
            :aria-label="$t('Project')"
            class="-mb-px flex gap-1 overflow-x-auto border-b"
        >
            <Link
                v-for="tab in tabs"
                :key="toUrl(tab.href)"
                :href="tab.href"
                prefetch
                :aria-current="isCurrentTab(tab) ? 'page' : undefined"
                :class="
                    cn(
                        'relative border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                        isCurrentTab(tab)
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )
                "
            >
                {{ tab.title }}
                <!-- In the tab's padding, so the tabs next to it don't move. -->
                <Spinner
                    v-if="isOpeningSlowly(tab.href)"
                    class="absolute top-1/2 -right-1 size-3 -translate-y-1/2"
                />
            </Link>
        </nav>

        <slot />
    </div>
</template>
