<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { riskLevelInWords } from '@/lib/packageRisk';
import type { RiskLevel } from '@/types';

defineProps<{
    level: Exclude<RiskLevel, 'none'>;
}>();
</script>

<template>
    <!-- The level is written out too, so it doesn't depend on seeing colours. -->
    <Badge v-if="level === 'red'" variant="destructive" data-test="risk-badge">
        {{ riskLevelInWords(level) }}
    </Badge>
    <Badge
        v-else
        variant="outline"
        class="border-transparent bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100"
        data-test="risk-badge"
    >
        {{ riskLevelInWords(level) }}
    </Badge>
</template>
