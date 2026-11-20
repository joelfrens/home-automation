<?php

namespace Joelfrens\HomeAutomation\Sensors;

/**
 * Time ranges for history charts. Each range averages readings into time "buckets"
 * so a chart gets roughly 200–350 points instead of tens of thousands of raw readings.
 */
enum Range: string
{
    case SixHours = '6h';
    case Day = '24h';
    case Week = '7d';

    public const DEFAULT = self::Day;

    public function hours(): int
    {
        return match ($this) {
            self::SixHours => 6,
            self::Day => 24,
            self::Week => 168,
        };
    }

    /** Seconds of readings averaged into one chart point. */
    public function bucketSeconds(): int
    {
        return match ($this) {
            self::SixHours => 120,  // 2-minute averages  → 180 points
            self::Day => 300,       // 5-minute averages  → 288 points
            self::Week => 1800,     // 30-minute averages → 336 points
        };
    }

    /** From a query-string value like "6h". Anything unknown or odd (arrays, null) gives the default. */
    public static function fromQuery(mixed $value): self
    {
        return (is_string($value) ? self::tryFrom($value) : null) ?? self::DEFAULT;
    }
}
