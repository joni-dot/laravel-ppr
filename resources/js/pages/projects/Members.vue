<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ProjectMemberController from '@/actions/App/Http/Controllers/Projects/ProjectMemberController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import AddMemberDialog from '@/components/projects/AddMemberDialog.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import UserStatusBadge from '@/components/users/UserStatusBadge.vue';
import { trans } from '@/lib/i18n';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    PersonOption,
    ProjectHeader,
    ProjectMember,
    ProjectRole,
    ProjectRoleOption,
} from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader }) =>
        projectBreadcrumbs(props.project, {
            title: trans('Members'),
            href: ProjectMemberController.index(props.project.id),
        }),
});

const props = defineProps<{
    project: ProjectHeader;
    members: ProjectMember[];
    roles: ProjectRoleOption[];
    userOptions?: PersonOption[];
}>();

const memberBeingRemoved = ref<ProjectMember | null>(null);

// Redraws the role dropdowns, so a refused change shows the saved role again.
const roleSelectsVersion = ref(0);

function roleLabel(role: ProjectRole): string {
    return props.roles.find((option) => option.value === role)?.label ?? '';
}

function changeRole(member: ProjectMember, role: ProjectRole): void {
    router.patch(
        ProjectMemberController.update.url([props.project.id, member.id]),
        { role },
        {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.role) {
                    toast.error(errors.role);
                }

                roleSelectsVersion.value++;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Members')" />

    <ProjectLayout :project="project">
        <div class="flex justify-end" v-if="project.can.manageMembers">
            <AddMemberDialog
                :project-id="project.id"
                :roles="roles"
                :user-options="userOptions"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Name') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Email') }}
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            {{ $t('Role in this project') }}
                        </th>
                        <th
                            v-if="project.can.manageMembers"
                            scope="col"
                            class="px-4 py-3"
                        >
                            <span class="sr-only">{{ $t('Actions') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="member in members" :key="member.id">
                        <td class="px-4 py-3 font-medium">
                            {{ member.name }}
                            <UserStatusBadge
                                v-if="member.status !== 'active'"
                                :status="member.status"
                                class="ml-2"
                            />
                        </td>
                        <td class="px-4 py-3">{{ member.email }}</td>
                        <td class="px-4 py-3">
                            <NativeSelect
                                v-if="project.can.manageMembers"
                                :key="`${member.id}-${roleSelectsVersion}`"
                                :model-value="member.role"
                                :aria-label="
                                    $t('Role of :name', { name: member.name })
                                "
                                class="max-w-48"
                                @update:model-value="
                                    changeRole(member, $event as ProjectRole)
                                "
                            >
                                <option
                                    v-for="option in roles"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </NativeSelect>
                            <template v-else>
                                {{ roleLabel(member.role) }}
                            </template>
                        </td>
                        <td
                            v-if="project.can.manageMembers"
                            class="px-4 py-3 text-right"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="
                                    $t('Remove :name from the project', {
                                        name: member.name,
                                    })
                                "
                                @click="memberBeingRemoved = member"
                            >
                                <Trash2 />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="members.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            {{ $t('No members yet.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ProjectLayout>

    <ConfirmActionDialog
        v-if="memberBeingRemoved"
        :action="
            ProjectMemberController.destroy.form([
                project.id,
                memberBeingRemoved.id,
            ])
        "
        :title="
            $t('Remove :name from the project?', {
                name: memberBeingRemoved.name,
            })
        "
        :description="
            $t(
                'They will no longer see this project, and packages they own or buy will have no owner or buyer. Their user account stays as it is.',
            )
        "
        :confirm-label="$t('Remove')"
        :open="true"
        @update:open="memberBeingRemoved = null"
    />
</template>
