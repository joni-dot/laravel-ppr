<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { trans } from '@/lib/i18n';
import type { UserStatus } from '@/types';

defineProps<{
    status: UserStatus;
}>();

const appearance: Record<
    UserStatus,
    { label: () => string; variant: BadgeVariants['variant'] }
> = {
    active: { label: () => trans('Active'), variant: 'secondary' },
    invited: { label: () => trans('Invited'), variant: 'outline' },
    deactivated: { label: () => trans('Deactivated'), variant: 'destructive' },
};
</script>

<template>
    <Badge :variant="appearance[status].variant">
        {{ appearance[status].label() }}
    </Badge>
</template>
