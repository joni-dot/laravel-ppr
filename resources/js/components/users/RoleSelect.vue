<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Role, RoleOption } from '@/types';

defineProps<{
    id: string;
    roles: RoleOption[];
    defaultValue: Role;
}>();
</script>

<template>
    <Select name="role" :default-value="defaultValue" required>
        <SelectTrigger :id="id" class="w-full">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="role in roles"
                :key="role.value"
                :value="role.value"
            >
                {{ role.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
