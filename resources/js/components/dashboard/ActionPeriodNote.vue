<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from '@/lib/i18n';
import { edit as editNotificationSettings } from '@/routes/notification-settings';

/**
 * Which dates the next actions cover: the person's own notification period, as for their date reminders.
 */
const props = defineProps<{
    notificationDays: number;
}>();

const period = computed(() => {
    switch (props.notificationDays) {
        case 0:
            return trans(
                'Only overdue dates of the packages you own or buy are shown, because your reminders of coming dates are off.',
            );
        case 1:
            return trans(
                'Overdue dates, and dates up to 1 day ahead, of the packages you own or buy.',
            );
        default:
            return trans(
                'Overdue dates, and dates up to :days days ahead, of the packages you own or buy.',
                { days: props.notificationDays },
            );
    }
});
</script>

<template>
    <p class="text-sm text-muted-foreground" data-test="action-period">
        {{ period }}
        <Link
            :href="editNotificationSettings()"
            class="underline underline-offset-4 hover:text-foreground"
        >
            {{ $t('Change the period in your notification settings') }}
        </Link>
    </p>
</template>
