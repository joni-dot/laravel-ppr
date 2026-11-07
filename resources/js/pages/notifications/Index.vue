<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCheck } from '@lucide/vue';
import { computed } from 'vue';
import MarkAllNotificationsReadController from '@/actions/App/Http/Controllers/MarkAllNotificationsReadController';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/Heading.vue';
import { notificationMessage } from '@/components/notifications/notificationMessages';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { trans } from '@/lib/i18n';
import type { InAppNotification, Paginated } from '@/types';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: trans('Notifications'),
                href: NotificationController.index(),
            },
        ],
    }),
});

defineProps<{
    notifications: Paginated<InAppNotification>;
}>();

const page = usePage();

const hasUnread = computed(() => page.props.unreadNotificationCount > 0);

function markAsRead(notification: InAppNotification): void {
    router.patch(
        NotificationController.update.url(notification.id),
        {},
        { preserveScroll: true },
    );
}

function markAllAsRead(): void {
    router.post(
        MarkAllNotificationsReadController.url(),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('Notifications')" />

    <div class="flex max-w-3xl flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Notifications')"
                :description="
                    $t('About the packages you look after, newest first')
                "
                class="mb-0"
            />
            <Button v-if="hasUnread" variant="outline" @click="markAllAsRead">
                <CheckCheck />
                {{ $t('Mark all as read') }}
            </Button>
        </div>

        <p
            v-if="notifications.data.length === 0"
            class="rounded-xl border p-6 text-sm text-muted-foreground"
        >
            {{ $t('You have no notifications.') }}
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="notification in notifications.data"
                :key="notification.id"
                :class="[
                    'flex items-start gap-3 px-4 py-3',
                    { 'bg-muted/60': !notification.isRead },
                ]"
                :data-test="
                    notification.isRead
                        ? 'read-notification'
                        : 'unread-notification'
                "
            >
                <span
                    :class="[
                        'mt-1.5 size-2 shrink-0 rounded-full',
                        notification.isRead ? 'bg-transparent' : 'bg-primary',
                    ]"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <Link
                        :href="NotificationController.show(notification.id)"
                        :class="[
                            'block text-sm hover:underline',
                            { 'font-medium': !notification.isRead },
                        ]"
                    >
                        <span v-if="!notification.isRead" class="sr-only">{{
                            $t('Unread:')
                        }}</span>
                        {{ notificationMessage(notification) }}
                    </Link>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ notification.projectName }} ·
                        <time :datetime="notification.createdAt">{{
                            formatDateTime(notification.createdAt)
                        }}</time>
                    </p>
                </div>
                <Button
                    v-if="!notification.isRead"
                    variant="ghost"
                    size="sm"
                    @click="markAsRead(notification)"
                >
                    {{ $t('Mark as read') }}
                </Button>
            </li>
        </ul>

        <Pagination :paginator="notifications" />
    </div>
</template>
