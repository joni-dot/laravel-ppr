<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { trans } from '@/lib/i18n';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    EditablePackage,
    Option,
    PackageStatus,
    PersonOption,
    ProjectCategory,
    ProjectHeader,
    PurchaseType,
} from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader; package: EditablePackage }) => {
        const { breadcrumbs } = projectBreadcrumbs(props.project, {
            title: trans('Packages'),
            href: PackageController.index(props.project.id),
        });

        return {
            breadcrumbs: [
                ...breadcrumbs,
                {
                    title: props.package.number,
                    href: PackageController.show([
                        props.project.id,
                        props.package.id,
                    ]),
                },
                {
                    title: trans('Edit'),
                    href: PackageController.edit([
                        props.project.id,
                        props.package.id,
                    ]),
                },
            ],
        };
    },
});

const props = defineProps<{
    project: ProjectHeader;
    package: EditablePackage;
    categories: ProjectCategory[];
    categoryRequired: boolean;
    statuses: Option<PackageStatus>[];
    purchaseTypes: Option<PurchaseType>[];
    canAssignPeople: boolean;
    ownerOptions: PersonOption[];
    buyerOptions: PersonOption[];
}>();

const form = useForm({
    title: props.package.title,
    category_id: props.package.categoryId,
    status: props.package.status,
    purchase_type: props.package.purchaseType,
    owner_id: props.package.ownerId,
    buyer_id: props.package.buyerId,
    quantity:
        props.package.quantity === null
            ? ''
            : String(Number(props.package.quantity)),
    unit: props.package.unit ?? '',
    description: props.package.description ?? '',
    notes: props.package.notes ?? '',
});

function save(): void {
    form.put(
        PackageController.update.url([props.project.id, props.package.id]),
    );
}
</script>

<template>
    <Head :title="$t('Edit :number', { number: package.number })" />

    <ProjectLayout :project="project">
        <form class="max-w-2xl space-y-10" @submit.prevent="save">
            <section class="space-y-4">
                <Heading
                    variant="small"
                    :title="$t('Edit :number', { number: package.number })"
                    :description="
                        $t(
                            'The package number stays the same, even if the category changes.',
                        )
                    "
                />

                <FormField
                    id="title"
                    :label="$t('Title')"
                    :error="form.errors.title"
                >
                    <Input
                        id="title"
                        v-model="form.title"
                        required
                        maxlength="200"
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="category_id"
                        :label="$t('Category')"
                        :error="form.errors.category_id"
                    >
                        <NativeSelect
                            id="category_id"
                            v-model="form.category_id"
                            :required="categoryRequired"
                        >
                            <option :value="null" :disabled="categoryRequired">
                                {{
                                    categoryRequired
                                        ? $t('Choose a category')
                                        : $t('No category')
                                }}
                            </option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{
                                    $t(':code – :name', {
                                        code: category.code,
                                        name: category.name,
                                    })
                                }}
                            </option>
                        </NativeSelect>
                    </FormField>

                    <FormField
                        id="status"
                        :label="$t('Status')"
                        :error="form.errors.status"
                    >
                        <NativeSelect
                            id="status"
                            v-model="form.status"
                            required
                        >
                            <option
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </NativeSelect>
                    </FormField>

                    <FormField
                        id="purchase_type"
                        :label="$t('Purchase type')"
                        :error="form.errors.purchase_type"
                    >
                        <NativeSelect
                            id="purchase_type"
                            v-model="form.purchase_type"
                        >
                            <option :value="null">{{ $t('Not set') }}</option>
                            <option
                                v-for="type in purchaseTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </NativeSelect>
                    </FormField>

                    <div class="grid grid-cols-[1fr_8rem] gap-2">
                        <FormField
                            id="quantity"
                            :label="$t('Quantity (optional)')"
                            :error="form.errors.quantity"
                        >
                            <Input
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                min="0.001"
                                step="0.001"
                            />
                        </FormField>
                        <FormField
                            id="unit"
                            :label="$t('Unit')"
                            :error="form.errors.unit"
                        >
                            <Input
                                id="unit"
                                v-model="form.unit"
                                maxlength="20"
                                :placeholder="$t('pcs')"
                            />
                        </FormField>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <Heading variant="small" :title="$t('People')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="owner_id"
                        :label="$t('Package owner')"
                        :hint="
                            canAssignPeople
                                ? $t(
                                      'Keeps the package up to date. Viewers can’t own packages.',
                                  )
                                : $t(
                                      'Only project managers and buyers can change this.',
                                  )
                        "
                        :error="form.errors.owner_id"
                    >
                        <NativeSelect
                            v-if="canAssignPeople"
                            id="owner_id"
                            v-model="form.owner_id"
                        >
                            <option :value="null">{{ $t('No owner') }}</option>
                            <option
                                v-for="person in ownerOptions"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{
                                    $t(':name (:email)', {
                                        name: person.name,
                                        email: person.email,
                                    })
                                }}
                            </option>
                        </NativeSelect>
                        <Input
                            v-else
                            id="owner_id"
                            :model-value="package.ownerName ?? $t('No owner')"
                            disabled
                        />
                    </FormField>

                    <FormField
                        id="buyer_id"
                        :label="$t('Responsible buyer')"
                        :error="form.errors.buyer_id"
                    >
                        <NativeSelect
                            v-if="canAssignPeople"
                            id="buyer_id"
                            v-model="form.buyer_id"
                        >
                            <option :value="null">{{ $t('No buyer') }}</option>
                            <option
                                v-for="person in buyerOptions"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{
                                    $t(':name (:email)', {
                                        name: person.name,
                                        email: person.email,
                                    })
                                }}
                            </option>
                        </NativeSelect>
                        <Input
                            v-else
                            id="buyer_id"
                            :model-value="package.buyerName ?? $t('No buyer')"
                            disabled
                        />
                    </FormField>
                </div>
            </section>

            <section class="space-y-4">
                <Heading variant="small" :title="$t('Details')" />

                <FormField
                    id="description"
                    :label="$t('Description (optional)')"
                    :error="form.errors.description"
                >
                    <Textarea
                        id="description"
                        v-model="form.description"
                        maxlength="5000"
                        rows="4"
                    />
                </FormField>

                <FormField
                    id="notes"
                    :label="$t('Notes (optional)')"
                    :error="form.errors.notes"
                >
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        maxlength="5000"
                        rows="4"
                    />
                </FormField>
            </section>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ $t('Save package') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link
                        :href="PackageController.show([project.id, package.id])"
                    >
                        {{ $t('Cancel') }}
                    </Link>
                </Button>
            </div>
        </form>
    </ProjectLayout>
</template>
