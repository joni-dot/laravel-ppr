<?php

namespace Tests\Unit\Scheduling;

use App\Scheduling\Days;
use PHPUnit\Framework\TestCase;

class DaysTest extends TestCase
{
    public function test_days_are_counted_in_whole_calendar_days()
    {
        $this->assertSame(0, Days::fromDate('1970-01-01'));
        $this->assertSame('2028-03-01', Days::add('2028-02-28', 2));
        $this->assertSame('2027-03-01', Days::add('2027-02-28', 1));
        // Daylight saving starts and ends on these days in Europe; that must not shift a date.
        $this->assertSame('2027-03-29', Days::add('2027-03-27', 2));
        $this->assertSame('2027-10-30', Days::add('2027-11-01', -2));
    }

    public function test_dates_stay_right_after_more_dates_than_it_remembers()
    {
        $days = range(0, 25000);

        $this->assertSame($days, array_map(fn (int $day) => Days::fromDate(Days::toDate($day)), $days));

        $this->assertSame('2027-06-11', Days::add('2027-06-01', 10));
    }
}
