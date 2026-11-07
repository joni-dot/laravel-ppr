<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Dashboard"
            description="Late packages, high-risk items and the next actions will show here once projects are added."
            class="mb-0"
        />
    </div>
</template>
