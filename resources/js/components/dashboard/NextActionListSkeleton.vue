<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

// Grey rows about as tall as real ones, so the page doesn't jump when the actions arrive.
const ROW_COUNT = 3;
</script>

<template>
    <div class="divide-y rounded-xl border" aria-busy="true">
        <span class="sr-only" role="status">{{ $t('Loading…') }}</span>
        <div
            v-for="row in ROW_COUNT"
            :key="row"
            class="flex justify-between gap-6 px-4 py-3"
            aria-hidden="true"
        >
            <div class="space-y-2">
                <Skeleton class="h-4 w-56 motion-reduce:animate-none" />
                <Skeleton class="h-4 w-40 motion-reduce:animate-none" />
            </div>
            <Skeleton class="h-4 w-32 motion-reduce:animate-none" />
        </div>
    </div>
</template>
