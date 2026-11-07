<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { RouteFormDefinition } from '@/wayfinder';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    action: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    confirmLabel: string;
}>();

const isOpen = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="action"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        {{ confirmLabel }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
