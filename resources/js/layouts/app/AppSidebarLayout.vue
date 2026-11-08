<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import { useNavigationState } from '@/composables/useNavigationState';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isSlow } = useNavigationState();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!--
                The header stays usable while a page loads, so the sidebar toggle and notifications still work.
                Each new page's top element is created afresh, so the fade-in on the direct children plays once
                per page change, but not when filters, deferred data or form errors update the same page.
            -->
            <div
                data-test="page-content"
                class="flex min-w-0 flex-1 flex-col motion-safe:transition-opacity motion-safe:duration-150 motion-safe:*:animate-page-fade-in"
                :class="{ 'opacity-60': isSlow }"
                :inert="isSlow"
                :aria-busy="isSlow ? 'true' : undefined"
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
