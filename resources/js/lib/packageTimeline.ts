import type { ScheduleMilestone } from '@/types';

/**
 * Where everything of a package's small timeline goes, in pixels. Only places the dates the server sent:
 * no date is worked out here. Dates are "YYYY-MM-DD" and counted as whole UTC days, so time zones never
 * shift a marker.
 */

export type TimelineMilestone = {
    id: number;
    name: string;
    label: string;
    isNeedDate: boolean;
    isDone: boolean;
    planned: string | null;
    // The actual date once it happened, else the expected date.
    current: string | null;
    plannedX: number | null;
    currentX: number | null;
    // When the current date is later than planned: the curve between the two markers.
    slipPath: string | null;
    labelPosition: TimelineLabel;
};

export type TimelineLabel = {
    x: number;
    y: number;
    anchor: 'start' | 'end';
    leaderX: number;
    leaderTop: number;
};

export type TimelineFlag = {
    x: number;
    labelX: number;
    labelY: number;
    anchor: 'start' | 'middle' | 'end';
};

export type TimelineTick = { x: number; date: string; unit: 'year' | 'month' };

export type TimelineLayout = {
    width: number;
    height: number;
    axisY: number;
    axisStart: number;
    axisEnd: number;
    // Where the today and need date lines start, above the axis.
    flagLineTop: number;
    milestones: TimelineMilestone[];
    ticks: TimelineTick[];
    today: TimelineFlag | null;
    needDate: TimelineFlag | null;
};

const MS_PER_DAY = 86_400_000;
const SIDE_MARGIN = 24;
const FONT_SIZE = 11;
// A rough average glyph width of the app's font at FONT_SIZE, enough to keep labels apart.
const GLYPH_WIDTH = 6.2;
const LABEL_GAP = 8;
const LANE_HEIGHT = 16;
const LONGEST_LABEL = 22;
const FLAG_ROW_HEIGHT = 13;
const MAX_SLIP_HEIGHT = 16;

export function dayNumber(isoDate: string): number {
    const [year, month, day] = isoDate.split('-').map(Number);

    return Date.UTC(year, month - 1, day) / MS_PER_DAY;
}

/**
 * @param texts the words for the today and need date flags, to keep them apart
 */
export function layoutTimeline(
    milestones: ScheduleMilestone[],
    options: {
        width: number;
        today: string;
        needDate: string | null;
        texts: { today: string; needDate: string };
    },
): TimelineLayout | null {
    const dated = milestones.filter(
        (milestone) =>
            currentDate(milestone) !== null || milestone.planned !== null,
    );

    if (dated.length === 0) {
        return null;
    }

    const { width, today, needDate, texts } = options;
    const axisStart = SIDE_MARGIN;
    const axisEnd = width - SIDE_MARGIN;
    const xOf = scale(allDates(dated, today, needDate), axisStart, axisEnd);

    const todayX = xOf(today);
    const needX = needDate === null ? null : xOf(needDate);
    const flags = placeFlags(todayX, needX, texts, width);
    const flagLineTop = flags.rows * FLAG_ROW_HEIGHT + 4;
    const axisY = flagLineTop + MAX_SLIP_HEIGHT + 8;

    const placed = placeLabels(
        dated.map((milestone) => {
            const current = currentDate(milestone);

            return {
                label: shortName(milestone.name),
                x: xOf(current ?? (milestone.planned as string)),
            };
        }),
        width,
        axisY + 34,
    );

    const timelineMilestones = dated.map(
        (milestone, index): TimelineMilestone => {
            const current = currentDate(milestone);
            const plannedX =
                milestone.planned === null ? null : xOf(milestone.planned);
            const currentX = current === null ? null : xOf(current);

            return {
                id: milestone.id,
                name: milestone.name,
                label: shortName(milestone.name),
                isNeedDate: milestone.isNeedDate,
                isDone: milestone.isDone,
                planned: milestone.planned,
                current,
                plannedX,
                currentX,
                slipPath:
                    plannedX !== null &&
                    currentX !== null &&
                    isLaterThanPlanned(milestone.planned, current)
                        ? slipCurve(plannedX, currentX, axisY)
                        : null,
                labelPosition: { ...placed[index], leaderTop: axisY + 8 },
            };
        },
    );

    const lowestLabel = Math.max(...placed.map((label) => label.y));

    return {
        width,
        height: lowestLabel + 5,
        axisY,
        axisStart,
        axisEnd,
        flagLineTop,
        milestones: timelineMilestones,
        ticks: ticksBetween(
            xOf,
            allDates(dated, today, needDate),
            axisEnd - axisStart,
        ),
        today: flags.today,
        needDate: flags.needDate,
    };
}

// Once a milestone happened its expected date is the actual date, so only one marker is needed.
function currentDate(milestone: ScheduleMilestone): string | null {
    return milestone.isDone ? milestone.actual : milestone.expected;
}

function isLaterThanPlanned(
    planned: string | null,
    current: string | null,
): boolean {
    return planned !== null && current !== null && current > planned;
}

function allDates(
    milestones: ScheduleMilestone[],
    today: string,
    needDate: string | null,
): number[] {
    const dates = [
        today,
        needDate,
        ...milestones.flatMap((milestone) => [
            milestone.planned,
            currentDate(milestone),
        ]),
    ];

    return dates.filter((date) => date !== null).map(dayNumber);
}

/**
 * A little padding on both ends, so markers at the ends aren't cut in half.
 */
function scale(
    days: number[],
    start: number,
    end: number,
): (date: string) => number {
    const first = Math.min(...days);
    const span = Math.max(...days) - first;
    const padding = Math.max(span * 0.03, 1);
    const from = first - padding;
    const length = span + 2 * padding;

    return (date) =>
        start + ((dayNumber(date) - from) / length) * (end - start);
}

function slipCurve(fromX: number, toX: number, axisY: number): string {
    const height = Math.min(MAX_SLIP_HEIGHT, Math.max(5, (toX - fromX) / 3));
    const middle = (fromX + toX) / 2;

    // A quadratic curve peaks at half the control point's height.
    return `M ${fromX} ${axisY} Q ${middle} ${axisY - 2 * height} ${toX} ${axisY}`;
}

function shortName(name: string): string {
    return name.length > LONGEST_LABEL
        ? `${name.slice(0, LONGEST_LABEL - 1).trimEnd()}…`
        : name;
}

function textWidth(text: string): number {
    return text.length * GLYPH_WIDTH;
}

type LabelBox = { from: number; to: number; lane: number; leaderX: number };

/**
 * Labels hang below the axis in lanes, each with a thin line up to its marker, in the highest lane where
 * it touches no other label and no line crosses it. Labels that start at their marker are placed from
 * right to left, so the lines of earlier milestones pass to the left of later labels. Labels too close to
 * the right edge end at their marker instead; they go below the others, from left to right, for the same
 * reason. If no lane avoids every crossing, a line may cross a label; the text has a halo.
 */
function placeLabels(
    labels: { label: string; x: number }[],
    width: number,
    firstLaneY: number,
): PlacedLabel[] {
    const boxes = labels.map(({ label, x }) =>
        labelBox(x, 4 + textWidth(label), width),
    );
    const startingAtMarker = boxes
        .filter((box) => box.anchor === 'start')
        .sort((a, b) => b.leaderX - a.leaderX);
    const endingAtMarker = boxes
        .filter((box) => box.anchor === 'end')
        .sort((a, b) => a.leaderX - b.leaderX);
    const placed: LabelBox[] = [];
    const lanes = new Map<(typeof boxes)[number], number>();

    for (const box of [...startingAtMarker, ...endingAtMarker]) {
        const lane = firstFreeLane(box, placed);
        placed.push({ ...box, lane });
        lanes.set(box, lane);
    }

    return boxes.map((box) => {
        const lane = lanes.get(box) ?? 0;

        return {
            x: box.anchor === 'start' ? box.leaderX + 4 : box.leaderX - 4,
            y: firstLaneY + lane * LANE_HEIGHT,
            anchor: box.anchor,
            leaderX: box.leaderX,
        };
    });
}

function labelBox(x: number, length: number, width: number) {
    return x + length <= width - 2
        ? { from: x, to: x + length, leaderX: x, anchor: 'start' as const }
        : { from: x - length, to: x, leaderX: x, anchor: 'end' as const };
}

type PlacedLabel = Omit<TimelineLabel, 'leaderTop'>;

function firstFreeLane(
    box: Omit<LabelBox, 'lane'>,
    placed: LabelBox[],
): number {
    const lanes = placed.length + 1;

    for (let lane = 0; lane < lanes; lane++) {
        if (
            placed.every(
                (other) =>
                    !overlaps(box, lane, other) && !crosses(box, lane, other),
            )
        ) {
            return lane;
        }
    }

    for (let lane = 0; ; lane++) {
        if (placed.every((other) => !overlaps(box, lane, other))) {
            return lane;
        }
    }
}

function overlaps(
    box: Omit<LabelBox, 'lane'>,
    lane: number,
    other: LabelBox,
): boolean {
    return (
        other.lane === lane &&
        box.from < other.to + LABEL_GAP &&
        other.from < box.to + LABEL_GAP
    );
}

// A label's line runs from the axis down to its lane, through every lane above it.
function crosses(
    box: Omit<LabelBox, 'lane'>,
    lane: number,
    other: LabelBox,
): boolean {
    if (other.lane === lane) {
        return false;
    }

    const [upper, lower] = other.lane < lane ? [other, box] : [box, other];

    return lower.leaderX > upper.from + 2 && lower.leaderX < upper.to - 2;
}

/**
 * The today and need date words sit above the axis; the second goes one row down when they would touch.
 */
function placeFlags(
    todayX: number,
    needX: number | null,
    texts: { today: string; needDate: string },
    width: number,
): { today: TimelineFlag; needDate: TimelineFlag | null; rows: number } {
    const today = flagAt(todayX, textWidth(texts.today), width, 1);

    if (needX === null) {
        return { today, needDate: null, rows: 1 };
    }

    const sameRow = flagAt(needX, textWidth(texts.needDate), width, 1);
    const apart =
        boxOf(sameRow, textWidth(texts.needDate)).from >
            boxOf(today, textWidth(texts.today)).to + LABEL_GAP ||
        boxOf(sameRow, textWidth(texts.needDate)).to + LABEL_GAP <
            boxOf(today, textWidth(texts.today)).from;

    return apart
        ? { today, needDate: sameRow, rows: 1 }
        : {
              today,
              needDate: flagAt(needX, textWidth(texts.needDate), width, 2),
              rows: 2,
          };
}

function flagAt(
    x: number,
    textLength: number,
    width: number,
    row: number,
): TimelineFlag {
    const half = textLength / 2;
    const anchor =
        x - half < 2 ? 'start' : x + half > width - 2 ? 'end' : 'middle';

    return {
        x,
        labelX:
            anchor === 'start'
                ? Math.max(x - 4, 2)
                : anchor === 'end'
                  ? Math.min(x + 4, width - 2)
                  : x,
        labelY: row * FLAG_ROW_HEIGHT - 2,
        anchor,
    };
}

function boxOf(
    flag: TimelineFlag,
    textLength: number,
): { from: number; to: number } {
    switch (flag.anchor) {
        case 'start':
            return { from: flag.labelX, to: flag.labelX + textLength };
        case 'end':
            return { from: flag.labelX - textLength, to: flag.labelX };
        case 'middle':
            return {
                from: flag.labelX - textLength / 2,
                to: flag.labelX + textLength / 2,
            };
    }
}

const YEAR_STEPS = [1, 2, 5, 10, 20, 25, 50];
const MONTH_STEPS = [1, 2, 3, 6];
const MIN_TICK_SPACING = 70;

/**
 * Years or months, as many as fit without crowding.
 */
function ticksBetween(
    xOf: (date: string) => number,
    days: number[],
    axisLength: number,
): TimelineTick[] {
    const maxTicks = Math.max(2, Math.floor(axisLength / MIN_TICK_SPACING));
    const first = new Date(Math.min(...days) * MS_PER_DAY);
    const last = new Date(Math.max(...days) * MS_PER_DAY);
    const months =
        (last.getUTCFullYear() - first.getUTCFullYear()) * 12 +
        last.getUTCMonth() -
        first.getUTCMonth();

    if (months > 24) {
        const step =
            YEAR_STEPS.find((years) => months / 12 / years <= maxTicks) ?? 100;
        const firstYear =
            Math.ceil(
                (first.getUTCFullYear() +
                    (first.getUTCMonth() > 0 || first.getUTCDate() > 1
                        ? 1
                        : 0)) /
                    step,
            ) * step;

        return range(firstYear, last.getUTCFullYear(), step).map((year) =>
            tick(xOf, isoDate(year, 0), 'year'),
        );
    }

    const step = MONTH_STEPS.find((count) => months / count <= maxTicks) ?? 12;
    const firstMonth =
        first.getUTCFullYear() * 12 +
        first.getUTCMonth() +
        (first.getUTCDate() > 1 ? 1 : 0);
    const lastMonth = last.getUTCFullYear() * 12 + last.getUTCMonth();

    return range(Math.ceil(firstMonth / step) * step, lastMonth, step).map(
        (month) =>
            tick(xOf, isoDate(Math.floor(month / 12), month % 12), 'month'),
    );
}

function tick(
    xOf: (date: string) => number,
    date: string,
    unit: TimelineTick['unit'],
): TimelineTick {
    return { x: xOf(date), date, unit };
}

function range(from: number, to: number, step: number): number[] {
    const values: number[] = [];

    for (let value = from; value <= to; value += step) {
        values.push(value);
    }

    return values;
}

function isoDate(year: number, monthIndex: number): string {
    return `${String(year).padStart(4, '0')}-${String(monthIndex + 1).padStart(2, '0')}-01`;
}

export const TIMELINE_FONT_SIZE = FONT_SIZE;
