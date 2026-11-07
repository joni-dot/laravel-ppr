<?php

namespace Tests\Unit\Risk;

use App\Enums\PackageStatus;
use App\Risk\AssignedPerson;
use App\Risk\PackageRisk;
use App\Risk\PackageRiskFacts;
use App\Risk\RiskLevel;
use App\Risk\RiskReason;
use App\Risk\RiskThresholds;
use App\Scheduling\MilestoneEntry;
use App\Scheduling\MilestoneList;
use App\Scheduling\PackageSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackageRiskTest extends TestCase
{
    private const string TODAY = '2027-03-10';

    // Milestones 1 Order placed, 2 Delivered, 3 Required on site (the need date).
    private MilestoneList $list;

    private RiskThresholds $thresholds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->list = new MilestoneList([1, 2, 3], needDateId: 3);
        $this->thresholds = new RiskThresholds(amberDays: 14, redDays: 0);
    }

    public function test_a_package_with_people_and_time_to_spare_is_not_at_risk()
    {
        $risk = $this->assess();

        $this->assertSame(RiskLevel::None, $risk->level);
        $this->assertSame([], $risk->reasons);
        $this->assertNull($risk->overdueMilestone);
    }

    /**
     * @return array<string, array{PackageStatus}>
     */
    public static function finishedStatuses(): array
    {
        return ['closed' => [PackageStatus::Closed], 'cancelled' => [PackageStatus::Cancelled]];
    }

    #[DataProvider('finishedStatuses')]
    public function test_closed_and_cancelled_packages_are_never_at_risk(PackageStatus $status)
    {
        $risk = $this->assess(
            status: $status,
            entries: [1 => new MilestoneEntry(planned: '2027-01-01')],
            float: -30,
            isFlagged: true,
            owner: AssignedPerson::Nobody,
            buyer: AssignedPerson::Deactivated,
        );

        $this->assertSame(RiskLevel::None, $risk->level);
        $this->assertSame([], $risk->reasons);
    }

    public function test_a_package_flagged_by_hand_is_red()
    {
        $risk = $this->assess(isFlagged: true);

        $this->assertSame(RiskLevel::Red, $risk->level);
        $this->assertSame([RiskReason::ManualFlag], $risk->reasons);
    }

    /**
     * Amber below 14 days of float, red below 0: "below" means the number itself is still fine.
     *
     * @return array<string, array{int, RiskLevel}>
     */
    public static function floatsAroundTheWarnings(): array
    {
        return [
            'well ahead' => [60, RiskLevel::None],
            'exactly the amber warning' => [14, RiskLevel::None],
            'a day below amber' => [13, RiskLevel::Amber],
            'exactly the red warning' => [0, RiskLevel::Amber],
            'a day below red' => [-1, RiskLevel::Red],
            'months late' => [-90, RiskLevel::Red],
        ];
    }

    #[DataProvider('floatsAroundTheWarnings')]
    public function test_the_float_turns_amber_and_red_below_the_project_warnings(int $float, RiskLevel $expected)
    {
        $risk = $this->assess(float: $float);

        $this->assertSame($expected, $risk->level);
        $this->assertSame($expected === RiskLevel::None ? [] : [RiskReason::Late], $risk->reasons);
    }

    public function test_warnings_below_zero_allow_some_lateness()
    {
        $this->thresholds = new RiskThresholds(amberDays: 0, redDays: -5);

        $this->assertSame(RiskLevel::None, $this->assess(float: 0)->level);
        $this->assertSame(RiskLevel::Amber, $this->assess(float: -5)->level);
        $this->assertSame(RiskLevel::Red, $this->assess(float: -6)->level);
    }

    public function test_a_milestone_that_has_not_happened_and_was_expected_before_today_is_overdue()
    {
        $risk = $this->assess(entries: [
            1 => new MilestoneEntry(planned: '2027-03-09'),
            2 => new MilestoneEntry(planned: '2027-03-01'),
            3 => new MilestoneEntry(planned: '2027-06-01'),
        ]);

        $this->assertSame(RiskLevel::Red, $risk->level);
        $this->assertSame([RiskReason::Overdue], $risk->reasons);
        // The first one in the milestone list, so the page names the next thing to act on.
        $this->assertSame(1, $risk->overdueMilestone?->milestoneId);
        $this->assertSame('2027-03-09', $risk->overdueMilestone->expected);
    }

    public function test_a_milestone_expected_today_is_not_overdue_yet()
    {
        $risk = $this->assess(entries: [1 => new MilestoneEntry(planned: self::TODAY), 3 => new MilestoneEntry(planned: '2027-06-01')]);

        $this->assertSame(RiskLevel::None, $risk->level);
    }

    public function test_a_milestone_that_happened_is_not_overdue()
    {
        $risk = $this->assess(entries: [
            1 => new MilestoneEntry(planned: '2027-01-01', actual: '2027-01-20'),
            3 => new MilestoneEntry(planned: '2027-06-01'),
        ]);

        $this->assertSame(RiskLevel::None, $risk->level);
        $this->assertNull($risk->overdueMilestone);
    }

    public function test_a_new_forecast_moves_a_milestone_out_of_overdue()
    {
        $risk = $this->assess(entries: [
            1 => new MilestoneEntry(planned: '2027-01-01', forecast: '2027-04-01'),
            3 => new MilestoneEntry(planned: '2027-06-01'),
        ]);

        $this->assertSame(RiskLevel::None, $risk->level);
    }

    public function test_without_a_planned_need_date_the_float_cannot_be_judged()
    {
        $risk = $this->assess(entries: [1 => new MilestoneEntry(planned: '2027-05-01')], float: null);

        $this->assertSame(RiskLevel::Amber, $risk->level);
        $this->assertSame([RiskReason::NoNeedDate], $risk->reasons);
    }

    /**
     * @return array<string, array{AssignedPerson, AssignedPerson, list<RiskReason>}>
     */
    public static function people(): array
    {
        return [
            'no owner' => [AssignedPerson::Nobody, AssignedPerson::Active, [RiskReason::NoOwner]],
            'deactivated owner' => [AssignedPerson::Deactivated, AssignedPerson::Active, [RiskReason::OwnerDeactivated]],
            'no buyer' => [AssignedPerson::Active, AssignedPerson::Nobody, [RiskReason::NoBuyer]],
            'deactivated buyer' => [AssignedPerson::Active, AssignedPerson::Deactivated, [RiskReason::BuyerDeactivated]],
            'nobody at all' => [AssignedPerson::Nobody, AssignedPerson::Nobody, [RiskReason::NoOwner, RiskReason::NoBuyer]],
        ];
    }

    /**
     * @param  list<RiskReason>  $reasons
     */
    #[DataProvider('people')]
    public function test_a_package_without_an_active_owner_or_buyer_is_amber(AssignedPerson $owner, AssignedPerson $buyer, array $reasons)
    {
        $risk = $this->assess(owner: $owner, buyer: $buyer);

        $this->assertSame(RiskLevel::Amber, $risk->level);
        $this->assertSame($reasons, $risk->reasons);
    }

    public function test_the_level_is_the_worst_reason_and_every_reason_is_listed_most_serious_first()
    {
        $risk = $this->assess(
            entries: [1 => new MilestoneEntry(planned: '2027-01-01')],
            float: null,
            isFlagged: true,
            owner: AssignedPerson::Deactivated,
            buyer: AssignedPerson::Nobody,
        );

        $this->assertSame(RiskLevel::Red, $risk->level);
        $this->assertSame(
            [RiskReason::ManualFlag, RiskReason::Overdue, RiskReason::NoNeedDate, RiskReason::OwnerDeactivated, RiskReason::NoBuyer],
            $risk->reasons,
        );
    }

    public function test_amber_reasons_together_stay_amber()
    {
        $risk = $this->assess(float: 5, owner: AssignedPerson::Nobody);

        $this->assertSame(RiskLevel::Amber, $risk->level);
        $this->assertSame([RiskReason::Late, RiskReason::NoOwner], $risk->reasons);
    }

    public function test_the_assigned_person_comes_from_the_user_id_and_deactivation()
    {
        $this->assertSame(AssignedPerson::Nobody, AssignedPerson::of(null, null));
        $this->assertSame(AssignedPerson::Active, AssignedPerson::of(7, null));
        $this->assertSame(AssignedPerson::Deactivated, AssignedPerson::of(7, '2027-01-01 10:00:00'));
    }

    /**
     * Defaults to a package with an owner, a buyer and plenty of float.
     *
     * @param  array<int, MilestoneEntry>|null  $entries
     */
    private function assess(
        PackageStatus $status = PackageStatus::Ordered,
        ?array $entries = null,
        ?int $float = 60,
        bool $isFlagged = false,
        AssignedPerson $owner = AssignedPerson::Active,
        AssignedPerson $buyer = AssignedPerson::Active,
    ): PackageRisk {
        $schedule = PackageSchedule::calculate($this->list, $entries ?? [3 => new MilestoneEntry(planned: '2027-06-01')]);

        return PackageRisk::assess(new PackageRiskFacts($status, $schedule, $float, $isFlagged, $owner, $buyer), $this->thresholds, self::TODAY);
    }
}
