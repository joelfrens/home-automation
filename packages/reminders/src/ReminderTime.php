<?php

namespace Joelfrens\HomeAutomation\Reminders;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Turns "2 days before the event, at 19:00" into an exact UTC moment.
 *
 * The calculation happens in the household's time zone, so clock changes are handled:
 * 19:00 on the evening before is 18:00 UTC in summer (BST) and 19:00 UTC in winter (GMT).
 */
final class ReminderTime
{
    public static function for(string $eventDate, int $daysBefore, string $time, string $timezone): CarbonImmutable
    {
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidArgumentException("Invalid reminder time [{$time}], expected HH:MM.");
        }

        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$eventDate} {$time}", $timezone)
            ->subDays($daysBefore)
            ->utc();
    }
}
