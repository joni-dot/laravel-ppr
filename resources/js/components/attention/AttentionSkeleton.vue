<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Stands in for the Overview tab's numbers and charts while they load, about as tall as they are, so the
 * page doesn't jump when they arrive.
 */
const TILE_COUNT = 7;
const BAR_WIDTHS = ['w-4/5', 'w-1/2', 'w-2/3'];
</script>

<template>
    <div class="grid gap-6" aria-busy="true" data-test="attention-skeleton">
        <span class="sr-only" role="status">{{ $t('Loading…') }}</span>
        <div class="grid gap-6" aria-hidden="true">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                <div
                    v-for="tile in TILE_COUNT"
                    :key="tile"
                    class="grid gap-2 rounded-xl border p-3"
                >
                    <Skeleton class="h-7 w-8 motion-reduce:animate-none" />
                    <Skeleton class="h-4 w-24 motion-reduce:animate-none" />
                </div>
            </div>
            <Skeleton class="h-5 w-full max-w-xl motion-reduce:animate-none" />
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="grid gap-4 rounded-xl border p-6">
                    <Skeleton class="h-5 w-48 motion-reduce:animate-none" />
                    <Skeleton
                        v-for="width in BAR_WIDTHS"
                        :key="width"
                        class="h-3 motion-reduce:animate-none"
                        :class="width"
                    />
                </div>
                <div class="grid gap-4 rounded-xl border p-6">
                    <Skeleton class="h-5 w-48 motion-reduce:animate-none" />
                    <Skeleton class="h-36 w-full motion-reduce:animate-none" />
                </div>
            </div>
        </div>
    </div>
</template>
