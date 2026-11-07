<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const modelValue = defineModel<string | number | null>();
</script>

<template>
    <div :class="cn('relative w-full', props.class)">
        <select
            v-model="modelValue"
            v-bind="$attrs"
            data-slot="native-select"
            class="h-9 w-full min-w-0 appearance-none rounded-md border border-input bg-transparent py-1 pr-9 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40 [&>option]:bg-popover [&>option]:text-popover-foreground"
        >
            <slot />
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
    </div>
</template>
