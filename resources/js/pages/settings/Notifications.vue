<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import NotificationSettingsController from '@/actions/App/Http/Controllers/Settings/NotificationSettingsController';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { trans } from '@/lib/i18n';
import { edit } from '@/routes/notification-settings';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: trans('Notification settings'),
                href: edit(),
            },
        ],
    }),
});

defineProps<{
    notificationDays: number;
    maxNotificationDays: number;
}>();
</script>

<template>
    <Head :title="$t('Notification settings')" />

    <h1 class="sr-only">{{ $t('Notification settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Notifications')"
            :description="
                $t(
                    'You are notified in the app when you become a package\'s owner or responsible buyer, and about the dates of the packages you own or buy.',
                )
            "
        />

        <Form
            v-bind="NotificationSettingsController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <FormField
                id="notification_days"
                :label="$t('Remind me this many days before a date')"
                :hint="
                    $t(
                        'With 7, you are reminded a week before a milestone of your package is expected. 0 turns these reminders off; you are still told when a date has passed.',
                    )
                "
                :error="errors.notification_days"
            >
                <Input
                    id="notification_days"
                    type="number"
                    name="notification_days"
                    class="max-w-32"
                    :default-value="notificationDays"
                    min="0"
                    :max="maxNotificationDays"
                    required
                />
            </FormField>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ $t('Save') }}
            </Button>
        </Form>
    </div>
</template>
