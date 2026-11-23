<?php

namespace Joelfrens\HomeAutomation\Reminders\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Remind us N days before at HH:MM". remind_at is that moment in UTC.
 *
 * @property int $id
 * @property int $event_id
 * @property int $days_before
 * @property string $time
 * @property CarbonImmutable $remind_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $skipped_at
 * @property-read Event $event
 */
class Reminder extends Model
{
    protected $fillable = ['days_before', 'time', 'remind_at', 'sent_at', 'skipped_at'];

    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'remind_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Not sent or skipped yet.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('sent_at')->whereNull('skipped_at');
    }
}
