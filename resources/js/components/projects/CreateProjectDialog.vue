<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { FolderPlus } from '@lucide/vue';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import FormField from '@/components/FormField.vue';
import PersonSelect from '@/components/projects/PersonSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { PersonOption } from '@/types';

defineProps<{
    managerOptions: PersonOption[] | undefined;
}>();

const isOpen = ref(false);

// The list of people is only fetched when it is needed, to keep the project list fast.
watch(isOpen, (open) => {
    if (open) {
        router.reload({ only: ['managerOptions'] });
    }
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button><FolderPlus /> {{ $t('New project') }}</Button>
        </DialogTrigger>

        <DialogContent>
            <Form
                v-bind="ProjectController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('New project') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'The project manager runs the project: they add members and set up numbering.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="project-name"
                    :label="$t('Name')"
                    :error="errors.name"
                >
                    <Input
                        id="project-name"
                        name="name"
                        required
                        maxlength="150"
                        autocomplete="off"
                    />
                </FormField>

                <FormField
                    id="project-code"
                    :label="$t('Project code')"
                    :hint="
                        $t(
                            'Short code used in package numbers, for example P100. Letters and digits only.',
                        )
                    "
                    :error="errors.code"
                >
                    <Input
                        id="project-code"
                        name="code"
                        required
                        maxlength="10"
                        autocomplete="off"
                    />
                </FormField>

                <FormField
                    id="project-manager"
                    :label="$t('Project manager')"
                    :error="errors.manager_id"
                >
                    <PersonSelect
                        id="project-manager"
                        name="manager_id"
                        :people="managerOptions"
                    />
                </FormField>

                <FormField
                    id="project-description"
                    :label="$t('Description (optional)')"
                    :error="errors.description"
                >
                    <Textarea
                        id="project-description"
                        name="description"
                        maxlength="2000"
                        rows="3"
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
                        {{ $t('Create project') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
