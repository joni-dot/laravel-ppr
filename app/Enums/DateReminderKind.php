<?php

namespace App\Enums;

/**
 * The two reminders about a milestone date. Stored in sent_reminders.kind.
 */
enum DateReminderKind: string
{
    // The expected date is within the person's notification period.
    case ComingUp = 'coming_up';
    // The expected date has passed and the milestone isn't marked done.
    case Overdue = 'overdue';

    /**
     * Which reminder a date this many days away calls for, if any.
     *
     * @param  int  $notificationDays  the person's own period; 0 turns "coming up" off, but never "overdue"
     */
    public static function forDaysLeft(int $daysLeft, int $notificationDays): ?self
    {
        return match (true) {
            $daysLeft < 0 => self::Overdue,
            $daysLeft <= $notificationDays && $notificationDays > 0 => self::ComingUp,
            default => null,
        };
    }
}
