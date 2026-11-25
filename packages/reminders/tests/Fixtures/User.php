<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Stand-in for the host app's User: only what the package relies on (ntfy routing). */
class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = [];

    public function routeNotificationForNtfy(): ?string
    {
        return $this->ntfy_topic;
    }
}
