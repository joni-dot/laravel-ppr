<?php

namespace Tests\Unit\Scheduling;

use App\Scheduling\SubPackageRollUp;
use PHPUnit\Framework\TestCase;

class SubPackageRollUpTest extends TestCase
{
    public function test_a_package_shows_the_latest_date_found_anywhere_below_it()
    {
        $rolledUp = SubPackageRollUp::latest([
            ['id' => 1, 'parentId' => null, 'isCancelled' => false, 'dates' => ['need' => '2027-05-01', 'order' => '2027-01-01']],
            ['id' => 2, 'parentId' => 1, 'isCancelled' => false, 'dates' => ['need' => '2027-04-01', 'order' => null]],
            ['id' => 3, 'parentId' => 2, 'isCancelled' => false, 'dates' => ['need' => '2027-06-01', 'order' => '2026-12-01']],
            ['id' => 4, 'parentId' => 1, 'isCancelled' => false, 'dates' => ['need' => null, 'order' => '2027-02-01']],
            ['id' => 5, 'parentId' => null, 'isCancelled' => false, 'dates' => ['need' => '2027-03-01', 'order' => null]],
        ]);

        $this->assertSame(['need' => '2027-06-01', 'order' => '2027-02-01'], $rolledUp[1]);
        $this->assertSame(['need' => '2027-06-01', 'order' => '2026-12-01'], $rolledUp[2]);
        $this->assertSame(['order' => '2027-02-01'], $rolledUp[4]);
        $this->assertSame(['need' => '2027-03-01'], $rolledUp[5]);
    }

    public function test_cancelled_sub_packages_and_everything_under_them_are_left_out()
    {
        $rolledUp = SubPackageRollUp::latest([
            ['id' => 1, 'parentId' => null, 'isCancelled' => false, 'dates' => ['need' => '2027-05-01']],
            ['id' => 2, 'parentId' => 1, 'isCancelled' => true, 'dates' => ['need' => '2027-09-01']],
            ['id' => 3, 'parentId' => 2, 'isCancelled' => false, 'dates' => ['need' => '2027-10-01']],
        ]);

        $this->assertSame(['need' => '2027-05-01'], $rolledUp[1]);
        $this->assertSame(['need' => '2027-10-01'], $rolledUp[2]);
    }
}
