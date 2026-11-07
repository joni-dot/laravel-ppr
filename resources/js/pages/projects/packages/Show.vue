<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Pencil } from '@lucide/vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import Heading from '@/components/Heading.vue';
import PackageStatusBadge from '@/components/packages/PackageStatusBadge.vue';
import QuickAddPackage from '@/components/packages/QuickAddPackage.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { trans } from '@/lib/i18n';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type {
    PackageDetails,
    PackageLink,
    ProjectCategory,
    ProjectHeader,
    SubPackageRow,
} from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader; package: PackageDetails }) => {
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
            ],
        };
    },
});

defineProps<{
    project: ProjectHeader;
    package: PackageDetails;
    ancestors: PackageLink[];
    subPackages: SubPackageRow[];
    categories: ProjectCategory[];
    can: { update: boolean; create: boolean };
}>();
</script>

<template>
    <Head :title="`${package.number} ${package.title}`" />

    <ProjectLayout :project="project">
        <nav
            v-if="ancestors.length > 0"
            :aria-label="$t('Packages above this one')"
            class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
        >
            <template v-for="ancestor in ancestors" :key="ancestor.id">
                <Link
                    :href="PackageController.show([project.id, ancestor.id])"
                    class="hover:text-foreground hover:underline"
                >
                    <span class="font-mono">{{ ancestor.number }}</span>
                    {{ ancestor.title }}
                </Link>
                <ChevronRight class="size-4" />
            </template>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <p class="font-mono text-sm text-muted-foreground">
                    {{ package.number }}
                </p>
                <Heading variant="small" :title="package.title" class="mb-0" />
            </div>
            <Button v-if="can.update" as-child>
                <Link :href="PackageController.edit([project.id, package.id])">
                    <Pencil />
                    {{ $t('Edit package') }}
                </Link>
            </Button>
        </div>

        <dl
            class="grid max-w-3xl grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-[auto_1fr]"
        >
            <dt class="text-muted-foreground">{{ $t('Status') }}</dt>
            <dd><PackageStatusBadge :status="package.status" /></dd>

            <dt class="text-muted-foreground">{{ $t('Category') }}</dt>
            <dd>
                {{
                    package.category
                        ? $t(':code – :name', {
                              code: package.category.code,
                              name: package.category.name,
                          })
                        : '–'
                }}
            </dd>

            <dt class="text-muted-foreground">{{ $t('Purchase type') }}</dt>
            <dd>{{ package.purchaseType ?? '–' }}</dd>

            <dt class="text-muted-foreground">{{ $t('Quantity') }}</dt>
            <dd>
                {{
                    package.quantity
                        ? `${Number(package.quantity)} ${package.unit ?? ''}`
                        : '–'
                }}
            </dd>

            <dt class="text-muted-foreground">{{ $t('Package owner') }}</dt>
            <dd>{{ package.ownerName ?? '–' }}</dd>

            <dt class="text-muted-foreground">
                {{ $t('Responsible buyer') }}
            </dt>
            <dd>{{ package.buyerName ?? '–' }}</dd>

            <dt class="text-muted-foreground">{{ $t('Description') }}</dt>
            <dd class="whitespace-pre-line">
                {{ package.description ?? '–' }}
            </dd>

            <dt class="text-muted-foreground">{{ $t('Notes') }}</dt>
            <dd class="whitespace-pre-line">{{ package.notes ?? '–' }}</dd>
        </dl>

        <section class="space-y-4">
            <Heading variant="small" :title="$t('Sub-packages')" class="mb-0" />

            <QuickAddPackage
                v-if="can.create"
                :project-id="project.id"
                :categories="categories"
                :parent="{
                    id: package.id,
                    number: package.number,
                    categoryId: package.category?.id ?? null,
                }"
            />

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Number') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Title') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Category') }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ $t('Status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="child in subPackages" :key="child.id">
                            <td class="px-4 py-2 font-mono whitespace-nowrap">
                                {{ child.number }}
                            </td>
                            <td class="px-4 py-2">
                                <Link
                                    :href="
                                        PackageController.show([
                                            project.id,
                                            child.id,
                                        ])
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ child.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-2">{{ child.categoryCode }}</td>
                            <td class="px-4 py-2">
                                <PackageStatusBadge :status="child.status" />
                            </td>
                        </tr>
                        <tr v-if="subPackages.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                {{ $t('No sub-packages.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </ProjectLayout>
</template>
