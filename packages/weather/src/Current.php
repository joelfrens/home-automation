<?php

namespace Joelfrens\HomeAutomation\Weather;

use Carbon\CarbonImmutable;

/**
 * The weather right now (Open-Meteo updates it every 15 minutes).
 */
final readonly class Current
{
    public function __construct(
        public float $temperature,
        public CarbonImmutable $time,
    ) {}

    /** @return array{temperature: float, time: string} */
    public function toArray(): array
    {
        return [
            'temperature' => $this->temperature,
            'time' => $this->time->toIso8601String(),
        ];
    }
}
