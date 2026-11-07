<script setup lang="ts">
import { NativeSelect } from '@/components/ui/native-select';
import type { ProjectRole, ProjectRoleOption } from '@/types';

defineProps<{
    id: string;
    roles: ProjectRoleOption[];
}>();

const role = defineModel<ProjectRole>({ required: true });
</script>

<template>
    <NativeSelect :id="id" v-model="role" name="role" required>
        <option
            v-for="option in roles"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </NativeSelect>
</template>
