<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { trans } from '@/lib/i18n';
import type { PackageStatus } from '@/types';

defineProps<{
    status: PackageStatus;
}>();

const appearance: Record<
    PackageStatus,
    { label: () => string; variant: BadgeVariants['variant'] }
> = {
    draft: { label: () => trans('Draft'), variant: 'outline' },
    planned: { label: () => trans('Planned'), variant: 'outline' },
    tendering: { label: () => trans('Tendering'), variant: 'secondary' },
    ordered: { label: () => trans('Ordered'), variant: 'default' },
    delivered: { label: () => trans('Delivered'), variant: 'default' },
    closed: { label: () => trans('Closed'), variant: 'secondary' },
    cancelled: { label: () => trans('Cancelled'), variant: 'destructive' },
};
</script>

<template>
    <Badge :variant="appearance[status].variant">
        {{ appearance[status].label() }}
    </Badge>
</template>
