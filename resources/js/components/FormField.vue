<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
}>();
</script>

<template>
    <div class="grid content-start gap-2">
        <Label :for="id">{{ label }}</Label>
        <slot />
        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>
