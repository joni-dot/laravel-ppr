<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, useTemplateRef } from 'vue';
import { formatDate } from '@/lib/format';
import { trans } from '@/lib/i18n';
import { layoutTimeline, TIMELINE_FONT_SIZE } from '@/lib/packageTimeline';
import type { TimelineMilestone, TimelineTick } from '@/lib/packageTimeline';
import type { PackageScheduleSummary, RiskLevel } from '@/types';

const props = defineProps<{
    schedule: PackageScheduleSummary;
    // The package's risk level from the server; it colours the slips of milestones not done yet.
    riskLevel: RiskLevel;
}>();

const figure = useTemplateRef<HTMLElement>('figure');
const { width: measuredWidth } = useElementSize(figure);

// Drawn at the real width, so the text keeps its size on a phone instead of shrinking with the drawing.
const width = computed(() =>
    Math.max(Math.round(measuredWidth.value) || 720, 280),
);

const texts = computed(() => ({
    today: trans('Today'),
    needDate: trans('Need date'),
}));

const layout = computed(() =>
    layoutTimeline(props.schedule.milestones, {
        width: width.value,
        today: props.schedule.today,
        needDate: props.schedule.needDate,
        texts: texts.value,
    }),
);

const slipClass = computed(() => {
    switch (props.riskLevel) {
        case 'red':
            return 'stroke-destructive';
        case 'amber':
            return 'stroke-amber-500 dark:stroke-amber-400';
        default:
            return 'stroke-muted-foreground';
    }
});

const yearFormat = new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    timeZone: 'UTC',
});
const monthFormat = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

function tickLabel(tick: TimelineTick): string {
    const date = new Date(`${tick.date}T00:00:00Z`);

    return (tick.unit === 'year' ? yearFormat : monthFormat).format(date);
}

function datesInWords(milestone: TimelineMilestone): string {
    const parts: string[] = [];

    if (milestone.planned !== null) {
        parts.push(
            trans('planned :date', { date: formatDate(milestone.planned) }),
        );
    }

    if (milestone.current !== null) {
        parts.push(
            milestone.isDone
                ? trans('actual :date', { date: formatDate(milestone.current) })
                : trans('expected :date', {
                      date: formatDate(milestone.current),
                  }),
        );
    }

    return trans(':milestone: :dates', {
        milestone: milestone.isNeedDate
            ? trans(':name (need date)', { name: milestone.name })
            : milestone.name,
        dates: parts.join(', '),
    });
}

const summary = computed(() =>
    layout.value === null
        ? ''
        : trans(
              'Timeline of the milestone dates. :milestones. Today is :today.',
              {
                  milestones: layout.value.milestones
                      .map(datesInWords)
                      .join('; '),
                  today: formatDate(props.schedule.today),
              },
          ),
);

// The check mark inside an actual date's marker, drawn around its centre.
function checkPath(x: number, y: number): string {
    return `M ${x - 3} ${y} l 2 2.2 l 4 -4.4`;
}
</script>

<template>
    <figure
        v-if="layout"
        ref="figure"
        class="space-y-2"
        data-test="package-timeline"
    >
        <svg
            role="img"
            :aria-label="summary"
            :viewBox="`0 0 ${layout.width} ${layout.height}`"
            :height="layout.height"
            class="block w-full overflow-visible"
            :font-size="TIMELINE_FONT_SIZE"
        >
            <line
                :x1="layout.axisStart"
                :x2="layout.axisEnd"
                :y1="layout.axisY"
                :y2="layout.axisY"
                class="stroke-border"
                stroke-width="2"
            />

            <g v-if="layout.needDate" data-test="timeline-need-date">
                <line
                    :x1="layout.needDate.x"
                    :x2="layout.needDate.x"
                    :y1="layout.flagLineTop"
                    :y2="layout.axisY + 8"
                    class="stroke-blue-600 dark:stroke-blue-400"
                    stroke-width="2"
                />
                <text
                    :x="layout.needDate.labelX"
                    :y="layout.needDate.labelY"
                    :text-anchor="layout.needDate.anchor"
                    class="fill-foreground font-medium"
                >
                    {{ texts.needDate }}
                </text>
            </g>

            <g v-if="layout.today" data-test="timeline-today">
                <line
                    :x1="layout.today.x"
                    :x2="layout.today.x"
                    :y1="layout.flagLineTop"
                    :y2="layout.axisY + 8"
                    class="stroke-foreground"
                    stroke-dasharray="3 3"
                />
                <text
                    :x="layout.today.labelX"
                    :y="layout.today.labelY"
                    :text-anchor="layout.today.anchor"
                    class="fill-foreground"
                >
                    {{ texts.today }}
                </text>
            </g>

            <g
                v-for="milestone in layout.milestones"
                :key="milestone.id"
                data-test="timeline-milestone"
            >
                <title>{{ datesInWords(milestone) }}</title>

                <line
                    :x1="milestone.labelPosition.leaderX"
                    :x2="milestone.labelPosition.leaderX"
                    :y1="milestone.labelPosition.leaderTop"
                    :y2="milestone.labelPosition.y - 9"
                    class="stroke-muted-foreground/50"
                />
                <text
                    :x="milestone.labelPosition.x"
                    :y="milestone.labelPosition.y"
                    :text-anchor="milestone.labelPosition.anchor"
                    class="fill-foreground stroke-background"
                    :class="{ 'font-semibold': milestone.isNeedDate }"
                    stroke-width="3"
                    paint-order="stroke"
                    stroke-linejoin="round"
                >
                    {{ milestone.label }}
                </text>

                <circle
                    v-if="milestone.plannedX !== null"
                    :cx="milestone.plannedX"
                    :cy="layout.axisY"
                    r="6"
                    class="fill-background stroke-muted-foreground"
                    stroke-width="2"
                />

                <path
                    v-if="milestone.slipPath"
                    :d="milestone.slipPath"
                    fill="none"
                    stroke-width="2"
                    stroke-linecap="round"
                    :class="
                        milestone.isDone ? 'stroke-muted-foreground' : slipClass
                    "
                    data-test="timeline-slip"
                />

                <template v-if="milestone.currentX !== null">
                    <g v-if="milestone.isDone">
                        <circle
                            :cx="milestone.currentX"
                            :cy="layout.axisY"
                            r="7"
                            class="fill-green-600 stroke-background dark:fill-green-500"
                            stroke-width="1.5"
                        />
                        <path
                            :d="checkPath(milestone.currentX, layout.axisY)"
                            fill="none"
                            class="stroke-white dark:stroke-background"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </g>
                    <circle
                        v-else
                        :cx="milestone.currentX"
                        :cy="layout.axisY"
                        r="4"
                        class="fill-foreground stroke-background"
                        stroke-width="1.5"
                    />
                </template>
            </g>

            <!-- After the milestones, so their lines pass under the dates. -->
            <g class="fill-muted-foreground" font-size="10">
                <g v-for="tick in layout.ticks" :key="tick.date">
                    <line
                        :x1="tick.x"
                        :x2="tick.x"
                        :y1="layout.axisY + 2"
                        :y2="layout.axisY + 6"
                        class="stroke-border"
                    />
                    <text
                        :x="tick.x"
                        :y="layout.axisY + 17"
                        text-anchor="middle"
                        class="stroke-background"
                        stroke-width="3"
                        paint-order="stroke"
                    >
                        {{ tickLabel(tick) }}
                    </text>
                </g>
            </g>
        </svg>

        <figcaption
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            aria-hidden="true"
        >
            <span class="inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="size-3.5">
                    <circle
                        cx="8"
                        cy="8"
                        r="5"
                        class="fill-background stroke-muted-foreground"
                        stroke-width="2"
                    />
                </svg>
                {{ $t('Planned') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="size-3.5">
                    <circle cx="8" cy="8" r="4" class="fill-foreground" />
                </svg>
                {{ $t('Expected') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="size-3.5">
                    <circle
                        cx="8"
                        cy="8"
                        r="7"
                        class="fill-green-600 dark:fill-green-500"
                    />
                    <path
                        :d="checkPath(8, 8)"
                        fill="none"
                        class="stroke-white dark:stroke-background"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                {{ $t('Actual') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="h-3.5 w-4">
                    <path
                        d="M 1 13 Q 8 1 15 13"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        :class="slipClass"
                    />
                </svg>
                {{ $t('Later than planned') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="size-3.5">
                    <line
                        x1="8"
                        x2="8"
                        y1="1"
                        y2="15"
                        class="stroke-foreground"
                        stroke-dasharray="3 3"
                    />
                </svg>
                {{ $t('Today') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="size-3.5">
                    <line
                        x1="8"
                        x2="8"
                        y1="1"
                        y2="15"
                        class="stroke-blue-600 dark:stroke-blue-400"
                        stroke-width="2"
                    />
                </svg>
                {{ $t('Need date') }}
            </span>
        </figcaption>
    </figure>
</template>
