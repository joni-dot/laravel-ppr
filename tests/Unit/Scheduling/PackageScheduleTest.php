<?php

namespace Tests\Unit\Scheduling;

use App\Scheduling\MilestoneEntry;
use App\Scheduling\MilestoneList;
use App\Scheduling\PackageSchedule;
use App\Scheduling\ScheduledMilestone;
use PHPUnit\Framework\TestCase;

class PackageScheduleTest extends TestCase
{
    // Milestones 1 Order placed, 2 Delivered, 3 Required on site (the need date), 4 Installed.
    private MilestoneList $list;

    // A "today" before every date below, so only the tests about today see it move anything.
    private const string LONG_AGO = '2000-01-01';

    private const string TODAY = '2027-03-10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->list = new MilestoneList([1, 2, 3, 4], needDateId: 3);
    }

    public function test_the_expected_date_is_the_actual_then_forecast_then_calculated_then_planned_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-01-01', actual: '2027-01-10'),
            2 => new MilestoneEntry(planned: '2027-05-01', forecast: '2027-06-01', leadDays: 100),
            3 => new MilestoneEntry(planned: '2027-07-01', leadDays: 14),
            4 => new MilestoneEntry(planned: '2027-09-01'),
        ], self::LONG_AGO);

        $this->assertSame(['2027-01-10', '2027-06-01', '2027-06-15', '2027-09-01'], $this->expected($schedule));
    }

    public function test_a_late_milestone_pushes_later_milestones_back_through_their_lead_times()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-01-01', forecast: '2027-02-01'),
            2 => new MilestoneEntry(planned: '2027-04-01', leadDays: 90),
            3 => new MilestoneEntry(planned: '2027-04-15', leadDays: 14),
        ], self::LONG_AGO);

        $this->assertSame(['2027-02-01', '2027-05-02', '2027-05-16', null], $this->expected($schedule));
        $this->assertSame(-31, $schedule->float());
    }

    public function test_latest_dates_count_back_from_the_need_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            2 => new MilestoneEntry(leadDays: 120),
            3 => new MilestoneEntry(planned: '2027-06-30', leadDays: 10),
            4 => new MilestoneEntry(planned: '2027-08-01', leadDays: 30),
        ], self::LONG_AGO);

        $this->assertSame(['2027-02-20', '2027-06-20', '2027-06-30', null], $this->latest($schedule));
        $this->assertSame('2027-06-30', $schedule->needDate());
    }

    public function test_a_missing_lead_time_leaves_earlier_latest_dates_unknown()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            3 => new MilestoneEntry(planned: '2027-06-30', leadDays: 10),
        ], self::LONG_AGO);

        $this->assertSame([null, '2027-06-20', '2027-06-30', null], $this->latest($schedule));
    }

    public function test_float_is_the_time_to_spare_before_the_need_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(actual: '2027-01-01'),
            2 => new MilestoneEntry(leadDays: 30),
            3 => new MilestoneEntry(planned: '2027-03-01', leadDays: 7),
        ], self::LONG_AGO);

        $this->assertSame('2027-02-07', $schedule->expectedNeedDate());
        $this->assertSame(22, $schedule->float());
    }

    public function test_float_is_unknown_without_a_need_date_or_a_need_date_milestone()
    {
        $this->assertNull(PackageSchedule::calculate($this->list, [1 => new MilestoneEntry(actual: '2027-01-01')], self::LONG_AGO)->float());

        $schedule = PackageSchedule::calculate(new MilestoneList([1, 2], needDateId: null), [1 => new MilestoneEntry(planned: '2027-01-01')], self::LONG_AGO);

        $this->assertNull($schedule->needDate());
        $this->assertSame([null, null], $this->latest($schedule));
    }

    public function test_the_next_milestone_is_the_first_one_without_an_actual_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(actual: '2027-01-01'),
            3 => new MilestoneEntry(actual: '2027-03-01'),
        ], self::LONG_AGO);

        $this->assertSame(2, $schedule->next()?->milestoneId);

        $done = array_fill_keys([1, 2, 3, 4], new MilestoneEntry(actual: '2027-01-01'));

        $this->assertNull(PackageSchedule::calculate($this->list, $done, self::LONG_AGO)->next());
    }

    public function test_dates_cross_daylight_saving_and_leap_days_without_shifting()
    {
        $schedule = PackageSchedule::calculate(new MilestoneList([1, 2], needDateId: 2), [
            1 => new MilestoneEntry(actual: '2028-02-28'),
            2 => new MilestoneEntry(planned: '2028-03-27', leadDays: 30),
        ], self::LONG_AGO);

        $this->assertSame('2028-03-29', $schedule->expectedNeedDate());
        $this->assertSame(-2, $schedule->float());
    }

    public function test_a_missed_milestone_moves_forward_with_today_and_pushes_later_ones_back()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-03-01'),
            2 => new MilestoneEntry(planned: '2027-04-01', leadDays: 30),
            3 => new MilestoneEntry(planned: '2027-04-15', leadDays: 14),
        ], self::TODAY);

        $this->assertSame([self::TODAY, '2027-04-09', '2027-04-23', null], $this->expected($schedule));
        $this->assertSame(-8, $schedule->float());
        // Tomorrow it is one more day late.
        $tomorrow = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-03-01'),
            2 => new MilestoneEntry(planned: '2027-04-01', leadDays: 30),
            3 => new MilestoneEntry(planned: '2027-04-15', leadDays: 14),
        ], '2027-03-11');
        $this->assertSame(-9, $tomorrow->float());
    }

    public function test_a_milestone_that_happened_keeps_its_actual_date_however_long_ago()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-01-01', actual: '2027-01-05'),
            2 => new MilestoneEntry(leadDays: 10),
        ], self::TODAY);

        // The next one would have been on 15 January; it hasn't happened, so it is expected today.
        $this->assertSame(['2027-01-05', self::TODAY, null, null], $this->expected($schedule));
    }

    public function test_a_finished_package_keeps_its_missed_dates_where_they_were()
    {
        $schedule = PackageSchedule::calculate($this->list, [1 => new MilestoneEntry(planned: '2027-03-01')], today: null);

        $this->assertSame(['2027-03-01', null, null, null], $this->expected($schedule));
    }

    public function test_the_due_date_is_the_forecast_else_the_planned_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-04-01', forecast: '2027-05-01'),
            2 => new MilestoneEntry(planned: '2027-06-01', leadDays: 10),
            3 => new MilestoneEntry(leadDays: 10),
        ], self::TODAY);

        $this->assertSame(['2027-05-01', '2027-06-01', null, null], array_map(fn (ScheduledMilestone $milestone) => $milestone->due(), $schedule->milestones()));
    }

    public function test_next_due_is_the_earliest_due_date_from_today_on()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-03-01'),
            2 => new MilestoneEntry(planned: '2027-03-20'),
            3 => new MilestoneEntry(forecast: '2027-03-15', planned: '2027-03-12'),
        ], self::TODAY);

        $this->assertSame(3, $schedule->nextDue(self::TODAY)?->milestoneId);
        $this->assertSame(1, $schedule->firstOverdue(self::TODAY)?->milestoneId);
    }

    /**
     * @return list<string|null>
     */
    private function expected(PackageSchedule $schedule): array
    {
        return array_map(fn (ScheduledMilestone $milestone) => $milestone->expected, $schedule->milestones());
    }

    /**
     * @return list<string|null>
     */
    private function latest(PackageSchedule $schedule): array
    {
        return array_map(fn (ScheduledMilestone $milestone) => $milestone->latest, $schedule->milestones());
    }
}
