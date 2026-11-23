<?php

namespace Joelfrens\HomeAutomation\Reminders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $colour a colour name the UI knows (sky, rose, amber, emerald, violet, slate)
 */
class Child extends Model
{
    public const COLOURS = ['sky', 'rose', 'amber', 'emerald', 'violet', 'slate'];

    protected $fillable = ['name', 'colour'];

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
