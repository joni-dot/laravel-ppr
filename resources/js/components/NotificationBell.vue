<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import { trans } from '@/lib/i18n';

const page = usePage();

const unread = computed(() => page.props.unreadNotificationCount);

const label = computed(() =>
    unread.value > 0
        ? trans('Notifications, :count unread', { count: unread.value })
        : trans('Notifications'),
);
</script>

<template>
    <Button variant="ghost" size="icon" class="relative" as-child>
        <Link
            :href="NotificationController.index()"
            :aria-label="label"
            :title="label"
            data-test="notification-bell"
        >
            <Bell />
            <span
                v-if="unread > 0"
                aria-hidden="true"
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-semibold text-white"
            >
                {{ unread > 99 ? '99+' : unread }}
            </span>
        </Link>
    </Button>
</template>
