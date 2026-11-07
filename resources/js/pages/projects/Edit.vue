<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import ProjectLayout from '@/components/projects/ProjectLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { trans } from '@/lib/i18n';
import { projectBreadcrumbs } from '@/lib/projectBreadcrumbs';
import type { ProjectHeader } from '@/types';

defineOptions({
    layout: (props: { project: ProjectHeader }) =>
        projectBreadcrumbs(props.project, {
            title: trans('Project settings'),
            href: ProjectController.edit(props.project.id),
        }),
});

defineProps<{
    project: ProjectHeader;
    details: {
        name: string;
        code: string;
        description: string | null;
        riskAmberDays: number;
        riskRedDays: number;
    };
}>();
</script>

<template>
    <Head :title="$t('Project settings')" />

    <ProjectLayout :project="project">
        <Form
            v-bind="ProjectController.update.form(project.id)"
            :options="{ preserveScroll: true }"
            class="max-w-xl space-y-10"
            v-slot="{ errors, processing }"
        >
            <section class="space-y-4">
                <Heading variant="small" :title="$t('Details')" />

                <FormField id="name" :label="$t('Name')" :error="errors.name">
                    <Input
                        id="name"
                        name="name"
                        :default-value="details.name"
                        required
                        maxlength="150"
                    />
                </FormField>

                <FormField
                    id="code"
                    :label="$t('Project code')"
                    :hint="
                        $t(
                            'Changing the code changes the numbers of new packages only.',
                        )
                    "
                    :error="errors.code"
                >
                    <Input
                        id="code"
                        name="code"
                        :default-value="details.code"
                        required
                        maxlength="10"
                    />
                </FormField>

                <FormField
                    id="description"
                    :label="$t('Description (optional)')"
                    :error="errors.description"
                >
                    <Textarea
                        id="description"
                        name="description"
                        :default-value="details.description ?? ''"
                        maxlength="2000"
                        rows="4"
                    />
                </FormField>
            </section>

            <section class="space-y-4">
                <Heading
                    variant="small"
                    :title="$t('Risk warnings')"
                    :description="
                        $t(
                            'A package turns amber or red when its spare time before it is needed drops below these numbers.',
                        )
                    "
                />

                <FormField
                    id="risk_amber_days"
                    :label="$t('Amber below (days of spare time)')"
                    :error="errors.risk_amber_days"
                >
                    <Input
                        id="risk_amber_days"
                        type="number"
                        name="risk_amber_days"
                        :default-value="details.riskAmberDays"
                        min="-365"
                        max="365"
                        required
                    />
                </FormField>

                <FormField
                    id="risk_red_days"
                    :label="$t('Red below (days of spare time)')"
                    :hint="
                        $t(
                            '0 means red as soon as a package is expected to arrive late.',
                        )
                    "
                    :error="errors.risk_red_days"
                >
                    <Input
                        id="risk_red_days"
                        type="number"
                        name="risk_red_days"
                        :default-value="details.riskRedDays"
                        min="-365"
                        max="365"
                        required
                    />
                </FormField>
            </section>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ $t('Save') }}
            </Button>
        </Form>
    </ProjectLayout>
</template>
