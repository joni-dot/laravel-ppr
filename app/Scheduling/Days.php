<?php

namespace App\Scheduling;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Calendar-day arithmetic on "YYYY-MM-DD" dates. Works in UTC whole days, so time zones and
 * daylight saving never shift a date. Plain numbers instead of Carbon, because the package list
 * does this tens of thousands of times.
 */
final class Days
{
    private const int SECONDS_PER_DAY = 86400;

    // Enough for decades of dates, small enough that a long-running worker never grows without limit.
    private const int REMEMBERED_DATES = 20000;

    /**
     * A package list converts the same few hundred dates tens of thousands of times, so each conversion is
     * remembered: that made the list's schedule columns several times faster.
     *
     * @var array<string, int>
     */
    private static array $daysByDate = [];

    /** @var array<int, string> */
    private static array $dateByDays = [];

    public static function fromDate(string $date): int
    {
        if (count(self::$daysByDate) >= self::REMEMBERED_DATES) {
            self::$daysByDate = [];
        }

        return self::$daysByDate[$date]
            ??= intdiv((new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp(), self::SECONDS_PER_DAY);
    }

    public static function toDate(int $days): string
    {
        if (count(self::$dateByDays) >= self::REMEMBERED_DATES) {
            self::$dateByDays = [];
        }

        return self::$dateByDays[$days] ??= gmdate('Y-m-d', $days * self::SECONDS_PER_DAY);
    }

    /**
     * Today in the time zone where the date is furthest ahead (UTC+14). Used as the limit for actual dates:
     * the app doesn't know each person's time zone, and in UTC someone in Finland couldn't enter
     * today's date during the first hours after midnight.
     */
    public static function latestToday(): string
    {
        return now('Pacific/Kiritimati')->toDateString();
    }

    public static function add(string $date, int $days): string
    {
        return self::toDate(self::fromDate($date) + $days);
    }
}
