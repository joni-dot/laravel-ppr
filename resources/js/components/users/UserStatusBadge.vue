<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import type { UserStatus } from '@/types';

defineProps<{
    status: UserStatus;
}>();

const appearance: Record<
    UserStatus,
    { label: string; variant: BadgeVariants['variant'] }
> = {
    active: { label: 'Active', variant: 'secondary' },
    invited: { label: 'Invited', variant: 'outline' },
    deactivated: { label: 'Deactivated', variant: 'destructive' },
};
</script>

<template>
    <Badge :variant="appearance[status].variant">
        {{ appearance[status].label }}
    </Badge>
</template>
