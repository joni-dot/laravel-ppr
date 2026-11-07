<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    paginator: Paginated<unknown>;
}>();
</script>

<template>
    <nav
        v-if="paginator.total > 0"
        aria-label="Pagination"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
    >
        <p>
            Showing {{ paginator.from }}–{{ paginator.to }} of
            {{ paginator.total }}
        </p>

        <div class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!paginator.prev_page_url"
                :as-child="!!paginator.prev_page_url"
            >
                <Link
                    v-if="paginator.prev_page_url"
                    :href="paginator.prev_page_url"
                    preserve-scroll
                    preserve-state
                >
                    <ChevronLeft /> Previous
                </Link>
                <template v-else><ChevronLeft /> Previous</template>
            </Button>

            <Button
                variant="outline"
                size="sm"
                :disabled="!paginator.next_page_url"
                :as-child="!!paginator.next_page_url"
            >
                <Link
                    v-if="paginator.next_page_url"
                    :href="paginator.next_page_url"
                    preserve-scroll
                    preserve-state
                >
                    Next <ChevronRight />
                </Link>
                <template v-else>Next <ChevronRight /></template>
            </Button>
        </div>
    </nav>
</template>
