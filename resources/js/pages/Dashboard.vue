<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { trans } from '@/lib/i18n';
import { dashboard } from '@/routes';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: trans('Dashboard'),
                href: dashboard(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Dashboard')"
            :description="
                $t(
                    'Late packages, high-risk items and the next actions will show here once projects are added.',
                )
            "
            class="mb-0"
        />
    </div>
</template>
