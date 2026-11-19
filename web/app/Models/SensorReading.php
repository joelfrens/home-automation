<?php

namespace App\Models;

use Database\Factories\SensorReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One BME280 reading, written by the Node ingestion service (Laravel only reads it).
 *
 * @property int $id
 * @property string $room
 * @property string $sensor
 * @property float $temperature_c
 * @property float $humidity_pct
 * @property float $pressure_hpa
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class SensorReading extends Model
{
    /** @use HasFactory<SensorReadingFactory> */
    use HasFactory;

    public $timestamps = false; // the table has recorded_at, not created_at/updated_at

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'temperature_c' => 'float',
            'humidity_pct' => 'float',
            'pressure_hpa' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
