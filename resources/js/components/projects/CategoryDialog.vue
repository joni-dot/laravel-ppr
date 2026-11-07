<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import ProjectCategoryController from '@/actions/App/Http/Controllers/Projects/ProjectCategoryController';
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
import type { ProjectCategory } from '@/types';

/**
 * Adds a category, or edits one when a category is given.
 */
const props = defineProps<{
    projectId: number;
    category?: ProjectCategory;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const action = props.category
    ? ProjectCategoryController.update.form([
          props.projectId,
          props.category.id,
      ])
    : ProjectCategoryController.store.form(props.projectId);
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
                    <DialogTitle>
                        {{
                            category ? $t('Edit category') : $t('Add category')
                        }}
                    </DialogTitle>
                </DialogHeader>

                <FormField
                    id="category-code"
                    :label="$t('Code')"
                    :hint="$t('For example ELE. Letters and digits only.')"
                    :error="errors.code"
                >
                    <Input
                        id="category-code"
                        name="code"
                        :default-value="category?.code"
                        required
                        maxlength="10"
                        autocomplete="off"
                    />
                </FormField>

                <FormField
                    id="category-name"
                    :label="$t('Name')"
                    :error="errors.name"
                >
                    <Input
                        id="category-name"
                        name="name"
                        :default-value="category?.name"
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
