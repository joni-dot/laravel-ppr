<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Flag, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import { toast } from 'vue-sonner';
import MoveProjectMilestoneController from '@/actions/App/Http/Controllers/Projects/MoveProjectMilestoneController';
import ProjectMilestoneController from '@/actions/App/Http/Controllers/Projects/ProjectMilestoneController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import RenameMilestoneDialog from '@/components/projects/RenameMilestoneDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { trans } from '@/lib/i18n';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type { ManagedMilestone, ProjectHeader } from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader }) =>
        projectBreadcrumbs(props.project, {
            title: trans('Milestones'),
            href: ProjectMilestoneController.index(props.project.id),
        }),
});

const props = defineProps<{
    project: ProjectHeader;
    milestones: ManagedMilestone[];
    maxMilestones: number;
}>();

const milestoneBeingRenamed = ref<ManagedMilestone | null>(null);
const milestoneBeingRemoved = ref<ManagedMilestone | null>(null);

const nameInput = useTemplateRef<InstanceType<typeof Input>>('name');

const form = useForm({ name: '' });

const isFull = computed(() => props.milestones.length >= props.maxMilestones);

function add(): void {
    form.post(ProjectMilestoneController.store.url(props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name');
            (nameInput.value?.$el as HTMLInputElement | undefined)?.focus();
        },
    });
}

function showFirstError(errors: Record<string, string>): void {
    const message = Object.values(errors)[0];

    if (message) {
        toast.error(message);
    }
}

function move(milestone: ManagedMilestone, direction: 'up' | 'down'): void {
    router.patch(
        MoveProjectMilestoneController.url([props.project.id, milestone.id]),
        { direction },
        { preserveScroll: true, onError: showFirstError },
    );
}

function makeNeedDate(milestone: ManagedMilestone): void {
    router.put(
        ProjectMilestoneController.update.url([props.project.id, milestone.id]),
        { is_need_date: true },
        { preserveScroll: true, onError: showFirstError },
    );
}

function removalDescription(milestone: ManagedMilestone): string {
    if (milestone.packagesWithDatesCount === 0) {
        return trans(
            'No package has dates or a lead time for this milestone yet.',
        );
    }

    return trans(
        "Packages that lose their dates and lead time for this milestone: :count. This can't be undone.",
        { count: milestone.packagesWithDatesCount },
    );
}
</script>

<template>
    <Head :title="$t('Milestones')" />

    <ProjectLayout :project="project">
        <section class="max-w-4xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Milestones')"
                :description="
                    $t(
                        'The steps every package in this project goes through, in order. The need date is when the project needs the package; it can\'t be removed, but another milestone can become the need date.',
                    )
                "
            />
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'A lead time counts the days from the previous milestone, so moving a milestone changes what its lead times mean.',
                    )
                }}
            </p>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Milestone') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ $t('Packages with dates') }}
                            </th>
                            <th scope="col" class="px-4 py-3">
                                <span class="sr-only">{{ $t('Actions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(milestone, index) in milestones"
                            :key="milestone.id"
                        >
                            <td class="px-4 py-2 font-medium">
                                <span data-test="milestone-name">{{
                                    milestone.name
                                }}</span>
                                <Badge
                                    v-if="milestone.isNeedDate"
                                    variant="secondary"
                                    class="ml-2"
                                >
                                    <Flag /> {{ $t('Need date') }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ milestone.packagesWithDatesCount }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="index === 0"
                                        :aria-label="
                                            $t('Move :name up', {
                                                name: milestone.name,
                                            })
                                        "
                                        @click="move(milestone, 'up')"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="
                                            index === milestones.length - 1
                                        "
                                        :aria-label="
                                            $t('Move :name down', {
                                                name: milestone.name,
                                            })
                                        "
                                        @click="move(milestone, 'down')"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="
                                            $t('Rename :name', {
                                                name: milestone.name,
                                            })
                                        "
                                        @click="
                                            milestoneBeingRenamed = milestone
                                        "
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="milestone.isNeedDate"
                                        :aria-label="
                                            $t('Make :name the need date', {
                                                name: milestone.name,
                                            })
                                        "
                                        @click="makeNeedDate(milestone)"
                                    >
                                        <Flag />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="milestone.isNeedDate"
                                        :aria-label="
                                            $t('Remove :name', {
                                                name: milestone.name,
                                            })
                                        "
                                        @click="
                                            milestoneBeingRemoved = milestone
                                        "
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="milestones.length === 0">
                            <td
                                colspan="3"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                {{ $t('No milestones yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="grid gap-2" @submit.prevent="add">
                <div class="flex flex-wrap items-start gap-2">
                    <Input
                        id="new-milestone-name"
                        ref="name"
                        v-model="form.name"
                        required
                        maxlength="100"
                        autocomplete="off"
                        :disabled="isFull"
                        :placeholder="$t('Name of the new milestone')"
                        :aria-label="$t('Name of the new milestone')"
                        :aria-invalid="form.errors.name ? true : undefined"
                        class="min-w-48 flex-1"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="isFull || form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        <Plus v-else />
                        {{ $t('Add milestone') }}
                    </Button>
                </div>
                <p v-if="isFull" class="text-sm text-muted-foreground">
                    {{
                        $t('A project can have at most :max milestones.', {
                            max: maxMilestones,
                        })
                    }}
                </p>
                <InputError :message="form.errors.name" />
            </form>
        </section>
    </ProjectLayout>

    <RenameMilestoneDialog
        v-if="milestoneBeingRenamed"
        :project-id="project.id"
        :milestone="milestoneBeingRenamed"
        :open="true"
        @update:open="milestoneBeingRenamed = null"
    />

    <ConfirmActionDialog
        v-if="milestoneBeingRemoved"
        :action="
            ProjectMilestoneController.destroy.form([
                project.id,
                milestoneBeingRemoved.id,
            ])
        "
        :title="
            $t('Remove the milestone :name?', {
                name: milestoneBeingRemoved.name,
            })
        "
        :description="removalDescription(milestoneBeingRemoved)"
        :confirm-label="$t('Remove')"
        :open="true"
        @update:open="milestoneBeingRemoved = null"
    />
</template>
