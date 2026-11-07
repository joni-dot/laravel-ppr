<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ProjectMemberController from '@/actions/App/Http/Controllers/Projects/ProjectMemberController';
import FormField from '@/components/FormField.vue';
import PersonSelect from '@/components/projects/PersonSelect.vue';
import ProjectRoleSelect from '@/components/projects/ProjectRoleSelect.vue';
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
import { Spinner } from '@/components/ui/spinner';
import type { PersonOption, ProjectRole, ProjectRoleOption } from '@/types';

const props = defineProps<{
    projectId: number;
    roles: ProjectRoleOption[];
    userOptions: PersonOption[] | undefined;
}>();

const isOpen = ref(false);
const role = ref<ProjectRole>('viewer');

const roleDescription = computed(
    () =>
        props.roles.find((option) => option.value === role.value)?.description,
);

// The list of people is only fetched when it is needed, and always fresh,
// so someone added a moment ago is not offered again.
watch(isOpen, (open) => {
    if (open) {
        role.value = 'viewer';
        router.reload({ only: ['userOptions'] });
    }
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button><UserPlus /> {{ $t('Add member') }}</Button>
        </DialogTrigger>

        <DialogContent>
            <Form
                v-bind="ProjectMemberController.store.form(projectId)"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('Add member') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Only members can see the project. To add someone new to the app, an admin invites them first.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="member-user"
                    :label="$t('Person')"
                    :error="errors.user_id"
                >
                    <PersonSelect
                        id="member-user"
                        name="user_id"
                        :people="userOptions"
                    />
                </FormField>

                <FormField
                    id="member-role"
                    :label="$t('Role in this project')"
                    :hint="roleDescription"
                    :error="errors.role"
                >
                    <ProjectRoleSelect
                        id="member-role"
                        v-model="role"
                        :roles="roles"
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
                        {{ $t('Add member') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
