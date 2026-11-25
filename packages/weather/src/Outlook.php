<?php

namespace Joelfrens\HomeAutomation\Weather;

/**
 * What the weather will be like during a time window, e.g. "07:45 to 08:45":
 * the coldest and warmest hour, and the highest chance of rain.
 */
final readonly class Outlook
{
    public function __construct(
        public float $minTemperature,
        public float $maxTemperature,
        public int $rainChance,  // percent, the highest of the hours in the window
        public int $hours,       // how many forecast hours the window covered
    ) {}

    /** @return array{minTemperature: float, maxTemperature: float, rainChance: int, hours: int} */
    public function toArray(): array
    {
        return [
            'minTemperature' => $this->minTemperature,
            'maxTemperature' => $this->maxTemperature,
            'rainChance' => $this->rainChance,
            'hours' => $this->hours,
        ];
    }
}
