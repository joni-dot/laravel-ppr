<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { computed } from 'vue';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { isMobile, state } = useSidebar();

// Worked out here instead of with a CSS :has() rule: such a rule made the browser re-check the whole
// page after every change, and scrolling the package list stuttered.
const sidebarShowsOnlyIcons = computed(
    () => !isMobile.value && state.value === 'collapsed',
);
</script>

<template>
    <header
        class="flex shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear md:px-4"
        :class="sidebarShowsOnlyIcons ? 'h-12' : 'h-16'"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto">
            <NotificationBell />
        </div>
    </header>
</template>
