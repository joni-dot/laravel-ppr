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
        ]);

        $this->assertSame(['2027-01-10', '2027-06-01', '2027-06-15', '2027-09-01'], $this->expected($schedule));
    }

    public function test_a_late_milestone_pushes_later_milestones_back_through_their_lead_times()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(planned: '2027-01-01', forecast: '2027-02-01'),
            2 => new MilestoneEntry(planned: '2027-04-01', leadDays: 90),
            3 => new MilestoneEntry(planned: '2027-04-15', leadDays: 14),
        ]);

        $this->assertSame(['2027-02-01', '2027-05-02', '2027-05-16', null], $this->expected($schedule));
        $this->assertSame(-31, $schedule->float());
    }

    public function test_latest_dates_count_back_from_the_need_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            2 => new MilestoneEntry(leadDays: 120),
            3 => new MilestoneEntry(planned: '2027-06-30', leadDays: 10),
            4 => new MilestoneEntry(planned: '2027-08-01', leadDays: 30),
        ]);

        $this->assertSame(['2027-02-20', '2027-06-20', '2027-06-30', null], $this->latest($schedule));
        $this->assertSame('2027-06-30', $schedule->needDate());
    }

    public function test_a_missing_lead_time_leaves_earlier_latest_dates_unknown()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            3 => new MilestoneEntry(planned: '2027-06-30', leadDays: 10),
        ]);

        $this->assertSame([null, '2027-06-20', '2027-06-30', null], $this->latest($schedule));
    }

    public function test_float_is_the_time_to_spare_before_the_need_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(actual: '2027-01-01'),
            2 => new MilestoneEntry(leadDays: 30),
            3 => new MilestoneEntry(planned: '2027-03-01', leadDays: 7),
        ]);

        $this->assertSame('2027-02-07', $schedule->expectedNeedDate());
        $this->assertSame(22, $schedule->float());
    }

    public function test_float_is_unknown_without_a_need_date_or_a_need_date_milestone()
    {
        $this->assertNull(PackageSchedule::calculate($this->list, [1 => new MilestoneEntry(actual: '2027-01-01')])->float());

        $schedule = PackageSchedule::calculate(new MilestoneList([1, 2], needDateId: null), [1 => new MilestoneEntry(planned: '2027-01-01')]);

        $this->assertNull($schedule->needDate());
        $this->assertSame([null, null], $this->latest($schedule));
    }

    public function test_the_next_milestone_is_the_first_one_without_an_actual_date()
    {
        $schedule = PackageSchedule::calculate($this->list, [
            1 => new MilestoneEntry(actual: '2027-01-01'),
            3 => new MilestoneEntry(actual: '2027-03-01'),
        ]);

        $this->assertSame(2, $schedule->next()?->milestoneId);

        $done = array_fill_keys([1, 2, 3, 4], new MilestoneEntry(actual: '2027-01-01'));

        $this->assertNull(PackageSchedule::calculate($this->list, $done)->next());
    }

    public function test_dates_cross_daylight_saving_and_leap_days_without_shifting()
    {
        $schedule = PackageSchedule::calculate(new MilestoneList([1, 2], needDateId: 2), [
            1 => new MilestoneEntry(actual: '2028-02-28'),
            2 => new MilestoneEntry(planned: '2028-03-27', leadDays: 30),
        ]);

        $this->assertSame('2028-03-29', $schedule->expectedNeedDate());
        $this->assertSame(-2, $schedule->float());
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
