<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectCategoryController from '@/actions/App/Http/Controllers/Projects/ProjectCategoryController';
import ProjectNumberingController from '@/actions/App/Http/Controllers/Projects/ProjectNumberingController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import CategoryDialog from '@/components/projects/CategoryDialog.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { trans } from '@/lib/i18n';
import {
    formatPackageNumber,
    formatSubPackageNumber,
} from '@/lib/packageNumber';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    NumberingScheme,
    ProjectCategory,
    ProjectHeader,
    SeparatorOption,
} from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader }) =>
        projectBreadcrumbs(props.project, {
            title: trans('Numbering'),
            href: ProjectNumberingController.edit(props.project.id),
        }),
});

const props = defineProps<{
    project: ProjectHeader;
    numbering: NumberingScheme;
    categories: ProjectCategory[];
    exampleCategoryCode: string;
    separators: SeparatorOption[];
    subSeparators: SeparatorOption[];
}>();

const form = useForm({
    number_prefix: props.numbering.prefix ?? '',
    number_includes_project_code: props.numbering.includesProjectCode,
    number_includes_category: props.numbering.includesCategory,
    number_separator: props.numbering.separator,
    number_padding: props.numbering.padding,
    number_start: props.numbering.start,
    sub_number_separator: props.numbering.subSeparator,
    sub_number_padding: props.numbering.subPadding,
});

const isAddingCategory = ref(false);
const categoryBeingEdited = ref<ProjectCategory | null>(null);
const categoryBeingRemoved = ref<ProjectCategory | null>(null);

function separatorCharacter(options: SeparatorOption[], value: string): string {
    return options.find((option) => option.value === value)?.character ?? '';
}

const previewPackageNumber = computed(() =>
    formatPackageNumber({
        prefix: form.number_prefix,
        projectCode: form.number_includes_project_code
            ? props.project.code
            : null,
        categoryCode: form.number_includes_category
            ? (props.categories[0]?.code ?? props.exampleCategoryCode)
            : null,
        separator: separatorCharacter(props.separators, form.number_separator),
        padding: Number(form.number_padding) || 1,
        runningNumber: Number(form.number_start) || 1,
    }),
);

const previewSubPackageNumber = computed(() =>
    formatSubPackageNumber(
        previewPackageNumber.value,
        separatorCharacter(props.subSeparators, form.sub_number_separator),
        1,
        Number(form.sub_number_padding) || 1,
    ),
);

function save(): void {
    form.put(ProjectNumberingController.update.url(props.project.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="$t('Numbering')" />

    <ProjectLayout :project="project">
        <div
            class="grid max-w-5xl gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]"
        >
            <form class="space-y-6" @submit.prevent="save">
                <Heading
                    variant="small"
                    :title="$t('Package numbers')"
                    :description="
                        $t(
                            'Every package gets the next number when it is added. Numbers are never reused. Changes here apply to packages added from now on.',
                        )
                    "
                />

                <div
                    class="rounded-lg border bg-muted/40 p-4"
                    aria-live="polite"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Preview') }}
                    </p>
                    <p
                        class="font-mono text-lg"
                        data-test="preview-package-number"
                    >
                        {{ previewPackageNumber }}
                    </p>
                    <p
                        class="font-mono text-sm text-muted-foreground"
                        data-test="preview-sub-package-number"
                    >
                        {{ previewSubPackageNumber }}
                    </p>
                </div>

                <FormField
                    id="number_prefix"
                    :label="$t('Prefix (optional)')"
                    :hint="$t('Letters and digits only, for example PKG.')"
                    :error="form.errors.number_prefix"
                >
                    <Input
                        id="number_prefix"
                        v-model="form.number_prefix"
                        maxlength="10"
                        autocomplete="off"
                    />
                </FormField>

                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="number_includes_project_code"
                            v-model="form.number_includes_project_code"
                        />
                        <Label for="number_includes_project_code">
                            {{
                                $t('Include the project code (:code)', {
                                    code: project.code,
                                })
                            }}
                        </Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="number_includes_category"
                            v-model="form.number_includes_category"
                        />
                        <Label for="number_includes_category">
                            {{ $t('Include the category code') }}
                        </Label>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-3">
                    <FormField
                        id="number_separator"
                        :label="$t('Separator')"
                        :error="form.errors.number_separator"
                    >
                        <NativeSelect
                            id="number_separator"
                            v-model="form.number_separator"
                        >
                            <option
                                v-for="option in separators"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </FormField>

                    <FormField
                        id="number_padding"
                        :label="$t('Digits')"
                        :error="form.errors.number_padding"
                    >
                        <Input
                            id="number_padding"
                            v-model.number="form.number_padding"
                            type="number"
                            min="1"
                            max="8"
                            required
                        />
                    </FormField>

                    <FormField
                        id="number_start"
                        :label="$t('First number')"
                        :hint="$t('Numbers already given are skipped.')"
                        :error="form.errors.number_start"
                    >
                        <Input
                            id="number_start"
                            v-model.number="form.number_start"
                            type="number"
                            min="1"
                            max="999999"
                            required
                        />
                    </FormField>
                </div>

                <div class="grid gap-6 sm:grid-cols-3">
                    <FormField
                        id="sub_number_separator"
                        :label="$t('Sub-package separator')"
                        :error="form.errors.sub_number_separator"
                    >
                        <NativeSelect
                            id="sub_number_separator"
                            v-model="form.sub_number_separator"
                        >
                            <option
                                v-for="option in subSeparators"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </FormField>

                    <FormField
                        id="sub_number_padding"
                        :label="$t('Sub-package digits')"
                        :error="form.errors.sub_number_padding"
                    >
                        <Input
                            id="sub_number_padding"
                            v-model.number="form.sub_number_padding"
                            type="number"
                            min="1"
                            max="4"
                            required
                        />
                    </FormField>
                </div>

                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ $t('Save numbering') }}
                </Button>
            </form>

            <section class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        :title="$t('Categories')"
                        :description="
                            $t(
                                'Group packages by trade or type. The code can be part of the number.',
                            )
                        "
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        @click="isAddingCategory = true"
                    >
                        <Plus /> {{ $t('Add category') }}
                    </Button>
                </div>

                <ul class="divide-y rounded-xl border text-sm">
                    <li
                        v-for="category in categories"
                        :key="category.id"
                        class="flex items-center gap-3 px-4 py-2"
                    >
                        <span class="w-16 font-mono">{{ category.code }}</span>
                        <span class="flex-1">{{ category.name }}</span>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="
                                $t('Edit :name', { name: category.name })
                            "
                            @click="categoryBeingEdited = category"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="
                                $t('Remove :name', { name: category.name })
                            "
                            @click="categoryBeingRemoved = category"
                        >
                            <Trash2 />
                        </Button>
                    </li>
                    <li
                        v-if="categories.length === 0"
                        class="px-4 py-6 text-center text-muted-foreground"
                    >
                        {{ $t('No categories yet.') }}
                    </li>
                </ul>
            </section>
        </div>
    </ProjectLayout>

    <CategoryDialog
        v-if="isAddingCategory"
        :project-id="project.id"
        :open="true"
        @update:open="isAddingCategory = false"
    />

    <CategoryDialog
        v-if="categoryBeingEdited"
        :project-id="project.id"
        :category="categoryBeingEdited"
        :open="true"
        @update:open="categoryBeingEdited = null"
    />

    <ConfirmActionDialog
        v-if="categoryBeingRemoved"
        :action="
            ProjectCategoryController.destroy.form([
                project.id,
                categoryBeingRemoved.id,
            ])
        "
        :title="
            $t('Remove the category :name?', {
                name: categoryBeingRemoved.name,
            })
        "
        :description="$t('Packages can no longer be put in this category.')"
        :confirm-label="$t('Remove')"
        :open="true"
        @update:open="categoryBeingRemoved = null"
    />
</template>
