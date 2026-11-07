<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import ProjectMilestoneController from '@/actions/App/Http/Controllers/Projects/ProjectMilestoneController';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { ManagedMilestone } from '@/types';

const props = defineProps<{
    projectId: number;
    milestone: ManagedMilestone;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const action = ProjectMilestoneController.update.form([
    props.projectId,
    props.milestone.id,
]);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="action"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('Rename milestone') }}</DialogTitle>
                </DialogHeader>

                <FormField
                    id="milestone-name"
                    :label="$t('Name')"
                    :error="errors.name"
                >
                    <Input
                        id="milestone-name"
                        name="name"
                        :default-value="milestone.name"
                        required
                        maxlength="100"
                        autocomplete="off"
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ $t('Cancel') }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ $t('Save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
