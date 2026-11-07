<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: SettingsController.edit() }],
    },
});

defineProps<{
    riskAmberDays: number;
    riskRedDays: number;
}>();
</script>

<template>
    <Head title="Settings" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading title="Settings" class="mb-0" />

        <Form
            v-bind="SettingsController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <section class="space-y-4">
                <Heading
                    variant="small"
                    title="Risk warnings"
                    description="Spare time is the number of days between a package's expected delivery and the date it is needed. These are the defaults for new projects; a project manager can change them for their project."
                />

                <FormField
                    id="risk_amber_days"
                    label="Amber below (days of spare time)"
                    :error="errors.risk_amber_days"
                >
                    <Input
                        id="risk_amber_days"
                        type="number"
                        name="risk_amber_days"
                        :default-value="riskAmberDays"
                        min="-365"
                        max="365"
                        required
                    />
                </FormField>

                <FormField
                    id="risk_red_days"
                    label="Red below (days of spare time)"
                    hint="0 means red as soon as a package is expected to arrive late."
                    :error="errors.risk_red_days"
                >
                    <Input
                        id="risk_red_days"
                        type="number"
                        name="risk_red_days"
                        :default-value="riskRedDays"
                        min="-365"
                        max="365"
                        required
                    />
                </FormField>
            </section>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Save
            </Button>
        </Form>
    </div>
</template>
