<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { trans } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editNotificationSettings } from '@/routes/notification-settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: trans('Profile'),
        href: editProfile(),
    },
    {
        title: trans('Security'),
        href: editSecurity(),
        // Opening it may ask for the password again, and Fortify tidies an unfinished two-factor set-up.
        prefetch: false,
    },
    {
        title: trans('Appearance'),
        href: editAppearance(),
    },
    {
        title: trans('Notifications'),
        href: editNotificationSettings(),
    },
]);

const { isCurrentOrParentUrl, isOpeningSlowly } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="$t('Settings')"
            :description="$t('Manage your profile and account settings')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    :aria-label="$t('Settings')"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link
                            :href="item.href"
                            :prefetch="item.prefetch ?? true"
                            :aria-current="
                                isCurrentOrParentUrl(item.href)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                            <Spinner
                                v-if="isOpeningSlowly(item.href)"
                                class="ml-auto"
                            />
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <!-- This layout stays while moving between settings pages, so the page inside needs its own fade-in. -->
                <section
                    class="max-w-xl space-y-12 motion-safe:*:animate-page-fade-in"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
