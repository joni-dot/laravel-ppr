<script setup lang="ts">
import { NativeSelect } from '@/components/ui/native-select';
import type { PersonOption } from '@/types';

defineProps<{
    id: string;
    name: string;
    people: PersonOption[] | undefined;
}>();
</script>

<template>
    <NativeSelect
        :id="id"
        :name="name"
        required
        :disabled="people === undefined"
    >
        <option value="" disabled selected>
            {{ people === undefined ? $t('Loading…') : $t('Choose a person') }}
        </option>
        <option v-for="person in people" :key="person.id" :value="person.id">
            {{
                $t(':name (:email)', { name: person.name, email: person.email })
            }}
        </option>
    </NativeSelect>
</template>
