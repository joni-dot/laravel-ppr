<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import RoleSelect from '@/components/users/RoleSelect.vue';
import type { ManagedUser, RoleOption } from '@/types';

defineProps<{
    user: ManagedUser;
    roles: RoleOption[];
}>();

const isOpen = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="UserController.update.form(user.id)"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Edit user</DialogTitle>
                    <DialogDescription>{{ user.email }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="edit-name">Name</Label>
                    <Input
                        id="edit-name"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-role">Role</Label>
                    <RoleSelect
                        id="edit-role"
                        :roles="roles"
                        :default-value="user.role"
                    />
                    <InputError :message="errors.role" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
