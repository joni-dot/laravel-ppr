<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { MoreHorizontal, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import DeactivateUserController from '@/actions/App/Http/Controllers/Admin/DeactivateUserController';
import ReactivateUserController from '@/actions/App/Http/Controllers/Admin/ReactivateUserController';
import ResendInvitationController from '@/actions/App/Http/Controllers/Admin/ResendInvitationController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import EditUserDialog from '@/components/users/EditUserDialog.vue';
import InviteUserDialog from '@/components/users/InviteUserDialog.vue';
import UserStatusBadge from '@/components/users/UserStatusBadge.vue';
import type { ManagedUser, Paginated, RoleOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index(),
            },
        ],
    },
});

const props = defineProps<{
    users: Paginated<ManagedUser>;
    filters: { search: string };
    roles: RoleOption[];
}>();

const currentUserId = usePage().props.auth.user.id;

const userBeingEdited = ref<ManagedUser | null>(null);
const userBeingDeactivated = ref<ManagedUser | null>(null);

const search = ref(props.filters.search);

const searchUsers = useDebounceFn((term: string) => {
    router.get(
        UserController.index.url(),
        { search: term || undefined },
        {
            only: ['users', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}, 300);

watch(search, searchUsers);

function roleLabel(user: ManagedUser): string {
    return props.roles.find((role) => role.value === user.role)?.label ?? '';
}

function hasActions(user: ManagedUser): boolean {
    return Object.values(user.can).some(Boolean);
}

function reactivate(user: ManagedUser): void {
    router.post(
        ReactivateUserController.url(user.id),
        {},
        { preserveScroll: true },
    );
}

function resendInvitation(user: ManagedUser): void {
    router.post(
        ResendInvitationController.url(user.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Users"
                description="Invite people and decide what they can do"
                class="mb-0"
            />
            <InviteUserDialog :roles="roles" />
        </div>

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Search by name or email"
                aria-label="Search users"
                class="pl-9"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Name</th>
                        <th scope="col" class="px-4 py-3 font-medium">Email</th>
                        <th scope="col" class="px-4 py-3 font-medium">Role</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Status
                        </th>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="px-4 py-3 font-medium">
                            {{ user.name }}
                            <span
                                v-if="user.id === currentUserId"
                                class="font-normal text-muted-foreground"
                                >(you)</span
                            >
                        </td>
                        <td class="px-4 py-3">{{ user.email }}</td>
                        <td class="px-4 py-3">{{ roleLabel(user) }}</td>
                        <td class="px-4 py-3">
                            <UserStatusBadge :status="user.status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <DropdownMenu v-if="hasActions(user)">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Actions for ${user.name}`"
                                    >
                                        <MoreHorizontal />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="user.can.update"
                                        @select="userBeingEdited = user"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="user.can.resendInvitation"
                                        @select="resendInvitation(user)"
                                    >
                                        Resend invitation
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="user.can.reactivate"
                                        @select="reactivate(user)"
                                    >
                                        Reactivate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="user.can.deactivate"
                                        variant="destructive"
                                        @select="userBeingDeactivated = user"
                                    >
                                        Deactivate
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No users found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="users" />
    </div>

    <EditUserDialog
        v-if="userBeingEdited"
        :user="userBeingEdited"
        :roles="roles"
        :open="true"
        @update:open="userBeingEdited = null"
    />

    <ConfirmActionDialog
        v-if="userBeingDeactivated"
        :action="DeactivateUserController.form(userBeingDeactivated.id)"
        :title="`Deactivate ${userBeingDeactivated.name}?`"
        description="They will be signed out right away and can't log in until an admin reactivates them. Nothing they created is deleted."
        confirm-label="Deactivate"
        :open="true"
        @update:open="userBeingDeactivated = null"
    />
</template>
