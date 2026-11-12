<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { DashboardAction } from '@/types';

/**
 * The milestones the person must act on, earliest first, as the server sorted them.
 */
defineProps<{
    actions: DashboardAction[];
    total: number;
}>();

function whenInWords(daysLeft: number): string {
    if (daysLeft === 0) {
        return trans('Today');
    }

    if (daysLeft > 0) {
        return daysLeft === 1
            ? trans('In 1 day')
            : trans('In :count days', { count: daysLeft });
    }

    return daysLeft === -1
        ? trans('1 day overdue')
        : trans(':count days overdue', { count: -daysLeft });
}

function roleInWords(action: DashboardAction): string {
    if (action.asOwner && action.asBuyer) {
        return trans('You own and buy it');
    }

    return action.asOwner ? trans('You own it') : trans('You buy it');
}
</script>

<template>
    <p
        v-if="actions.length === 0"
        class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
        data-test="no-actions"
    >
        {{ $t('Nothing needs you right now.') }}
    </p>

    <template v-else>
        <ul class="divide-y rounded-xl border" data-test="next-actions">
            <li
                v-for="action in actions"
                :key="`${action.packageId}-${action.milestoneName}-${action.date}`"
                :class="
                    cn(
                        'flex flex-wrap items-start justify-between gap-x-6 gap-y-1 px-4 py-3 text-sm',
                        action.kind === 'overdue' &&
                            'border-l-4 border-l-destructive bg-destructive/5',
                    )
                "
                data-test="next-action"
                :data-kind="action.kind"
            >
                <div class="min-w-0">
                    <Link
                        :href="
                            PackageController.show([
                                action.projectId,
                                action.packageId,
                            ])
                        "
                        class="font-medium hover:underline"
                    >
                        <span class="font-mono">{{ action.number }}</span>
                        {{ action.title }}
                    </Link>
                    <p class="text-muted-foreground">
                        {{ action.projectCode }} · {{ action.milestoneName }} ·
                        {{ roleInWords(action) }}
                    </p>
                </div>
                <p
                    :class="
                        cn(
                            'text-right tabular-nums',
                            action.kind === 'overdue'
                                ? 'font-medium text-destructive'
                                : 'text-muted-foreground',
                        )
                    "
                >
                    {{ formatDate(action.date) }} ·
                    <span data-test="action-when">{{
                        whenInWords(action.daysLeft)
                    }}</span>
                </p>
            </li>
        </ul>
        <p
            v-if="total > actions.length"
            class="text-sm text-muted-foreground"
            data-test="more-actions"
        >
            {{
                $t('Showing the first :shown of :total.', {
                    shown: actions.length,
                    total,
                })
            }}
        </p>
    </template>
</template>
