<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';

/**
 * A number that opens the packages it counts. Zero opens nothing, so it is plain text.
 */
defineProps<{
    count: number;
    href: NonNullable<InertiaLinkProps['href']>;
}>();
</script>

<template>
    <Link
        v-if="count > 0"
        :href="href"
        class="rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
        <slot />
    </Link>
    <span v-else>
        <slot />
    </span>
</template>
