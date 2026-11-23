<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Who may listen to which private channel. The browser asks POST /broadcasting/auth before
 * subscribing; guests are always refused, and these callbacks decide for logged-in users.
 */

// Live sensor readings on the dashboard: any family member.
Broadcast::channel('readings', fn (User $user): bool => true);

// Personal notifications (Laravel's default channel): only the user themselves.
Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => (string) $user->id === $id);
