<script setup lang="ts">
import { computed } from 'vue';
import { riskBadge } from '@/lib/packageBadges';
import type { RiskLevel } from '@/types';

const props = defineProps<{
    level: Exclude<RiskLevel, 'none'>;
}>();

const badge = computed(() => riskBadge(props.level));
</script>

<template>
    <span data-slot="badge" data-test="risk-badge" :class="badge.className">
        {{ badge.label }}
    </span>
</template>
