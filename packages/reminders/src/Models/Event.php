<?php

namespace Joelfrens\HomeAutomation\Reminders\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something on the family calendar: a school trip, PE day, a payment deadline...
 * starts_at/ends_at are stored in UTC; local() converts to the household time zone.
 *
 * @property int $id
 * @property int|null $child_id
 * @property string $title
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $all_day
 * @property string|null $location
 * @property string|null $needs
 * @property string|null $notes
 * @property string|null $source_path
 * @property int|null $created_by
 * @property-read Child|null $child
 */
class Event extends Model
{
    protected $fillable = [
        'child_id', 'title', 'starts_at', 'ends_at', 'all_day', 'location', 'needs', 'notes', 'source_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
        ];
    }

    /** @return BelongsTo<Child, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)->orderBy('remind_at');
    }

    /** The start in the household's time zone. */
    public function localStart(): CarbonImmutable
    {
        return $this->starts_at->setTimezone(config('reminders.timezone'));
    }

    /**
     * After this moment a reminder is pointless: the start for timed events, the end of the
     * day for all-day ones (a 07:30 "bring your PE kit" on an all-day event must still go out).
     */
    public function reminderDeadline(): CarbonImmutable
    {
        return $this->all_day
            ? $this->localStart()->endOfDay()->utc()
            : $this->starts_at;
    }

    public function isOver(CarbonInterface $now): bool
    {
        return $now->greaterThanOrEqualTo($this->reminderDeadline());
    }
}
