<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { ref } from 'vue';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import RoleSelect from '@/components/users/RoleSelect.vue';
import type { RoleOption } from '@/types';

defineProps<{
    roles: RoleOption[];
    invitationLifetimeInDays: number;
}>();

const isOpen = ref(false);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button><UserPlus /> {{ $t('Invite user') }}</Button>
        </DialogTrigger>

        <DialogContent>
            <Form
                v-bind="UserController.store.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('Invite user') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                "We'll email them a link to set their password. The link works for :days days.",
                                { days: invitationLifetimeInDays },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="invite-name">{{ $t('Name') }}</Label>
                    <Input
                        id="invite-name"
                        name="name"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="invite-email">{{ $t('Email address') }}</Label>
                    <Input
                        id="invite-email"
                        type="email"
                        name="email"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="invite-role">{{ $t('Role') }}</Label>
                    <RoleSelect
                        id="invite-role"
                        :roles="roles"
                        default-value="member"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Admins manage users, settings and every project. Members get their rights from their role in each project.',
                            )
                        }}
                    </p>
                    <InputError :message="errors.role" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ $t('Cancel') }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ $t('Send invitation') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
