<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header
        :class="cn(variant === 'small' ? '' : 'mb-8 space-y-0.5', props.class)"
    >
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base font-medium'
                    : 'text-xl font-semibold tracking-tight'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
