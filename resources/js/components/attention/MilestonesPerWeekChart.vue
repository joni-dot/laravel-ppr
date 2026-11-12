<script setup lang="ts">
import { computed } from 'vue';
import { formatDate, formatShortDate } from '@/lib/format';
import type { WeekCount } from '@/types';

/**
 * How many milestones are expected each week, from this week on, as vertical bars. Each bar is labelled
 * with its week's Monday and shows its count; screen readers get the same numbers as a table.
 */
const props = defineProps<{
    weeks: WeekCount[];
}>();

// The tallest bar, in rem; the count above it and the date below have room of their own.
const PLOT_HEIGHT_REM = 8;

const largestCount = computed(() =>
    Math.max(1, ...props.weeks.map((week) => week.count)),
);

const hasMilestones = computed(() =>
    props.weeks.some((week) => week.count > 0),
);

function heightOf(count: number): string {
    return `${(count / largestCount.value) * PLOT_HEIGHT_REM}rem`;
}
</script>

<template>
    <div class="grid gap-3">
        <template v-if="hasMilestones">
            <div aria-hidden="true" data-test="weeks-chart">
                <ol
                    class="grid grid-cols-12 items-end gap-1 border-b"
                    :style="{ height: `${PLOT_HEIGHT_REM + 1.5}rem` }"
                >
                    <li
                        v-for="week in weeks"
                        :key="week.weekStart"
                        class="flex flex-col items-center justify-end gap-0.5"
                        data-test="week-bar"
                    >
                        <span class="text-xs tabular-nums">
                            {{ week.count }}
                        </span>
                        <span
                            class="w-full max-w-8 rounded-t-sm bg-primary/70"
                            :style="{ height: heightOf(week.count) }"
                        />
                    </li>
                </ol>
                <ol class="mt-1 grid grid-cols-12 gap-1">
                    <li
                        v-for="week in weeks"
                        :key="week.weekStart"
                        class="text-center text-[0.7rem] leading-tight text-muted-foreground"
                    >
                        {{ formatShortDate(week.weekStart) }}
                    </li>
                </ol>
            </div>
            <table class="sr-only">
                <caption>
                    {{
                        $t('Milestones due per week')
                    }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">{{ $t('Week starting') }}</th>
                        <th scope="col">{{ $t('Milestones due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="week in weeks" :key="week.weekStart">
                        <td>{{ formatDate(week.weekStart) }}</td>
                        <td>{{ week.count }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
        <p v-else class="text-sm text-muted-foreground">
            {{
                $t('No milestones are expected in the next :weeks weeks.', {
                    weeks: weeks.length,
                })
            }}
        </p>
        <p class="text-xs text-muted-foreground">
            {{
                $t(
                    'Overdue milestones are counted in the Overdue number above.',
                )
            }}
        </p>
    </div>
</template>
