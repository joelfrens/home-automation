<?php

namespace Joelfrens\HomeAutomation\Sensors\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Joelfrens\HomeAutomation\Sensors\Database\Factories\MeasurementFactory;

/**
 * One value of one metric from one sensor at one moment ("long" / "narrow" table).
 * A BME280 message becomes three rows (temperature, humidity, pressure) with the same recorded_at.
 * Rows are written by the Node ingestion service; Laravel only reads them.
 *
 * @property int $id
 * @property Carbon $recorded_at
 * @property string $room
 * @property string $sensor
 * @property string $metric
 * @property float $value
 * @property string $unit
 */
class Measurement extends Model
{
    /** @use HasFactory<MeasurementFactory> */
    use HasFactory;

    public $timestamps = false; // only recorded_at, no created_at/updated_at

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MeasurementFactory
    {
        return MeasurementFactory::new();
    }
}
