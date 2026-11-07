<?php

namespace App\Scheduling;

/**
 * A package isn't finished until its sub-packages are, so a parent shows the latest date found
 * anywhere below it. Cancelled packages, and everything under them, are left out.
 */
final class SubPackageRollUp
{
    /**
     * @param  list<array{id: int, parentId: int|null, isCancelled: bool, dates: array<int|string, string|null>}>  $rows
     *                                                                                                                    in tree order: every package before its sub-packages
     * @return array<int, array<int|string, string>> per package id, the latest date for each key among the package and the packages under it
     */
    public static function latest(array $rows): array
    {
        $fromBelow = [];
        $rolledUp = [];

        foreach (array_reverse($rows) as $row) {
            $dates = self::later(array_filter($row['dates'], fn (?string $date) => $date !== null), $fromBelow[$row['id']] ?? []);
            $rolledUp[$row['id']] = $dates;

            if ($row['parentId'] !== null && ! $row['isCancelled']) {
                $fromBelow[$row['parentId']] = self::later($fromBelow[$row['parentId']] ?? [], $dates);
            }
        }

        return $rolledUp;
    }

    /**
     * @param  array<int|string, string>  $first
     * @param  array<int|string, string>  $second
     * @return array<int|string, string>
     */
    private static function later(array $first, array $second): array
    {
        foreach ($second as $key => $date) {
            if (! isset($first[$key]) || $date > $first[$key]) {
                $first[$key] = $date;
            }
        }

        return $first;
    }
}
