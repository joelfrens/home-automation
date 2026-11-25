<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Joelfrens\HomeAutomation\Reminders\Notifications\NtfyTest;
use Throwable;

/**
 * "Send test notification" (Settings → Profile): pushes a hello to the user's own phone,
 * so they know the ntfy app is subscribed to the right topic.
 */
class NtfyTestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (blank($user->ntfy_topic)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Save a notification topic first.')]);

            return to_route('profile.edit');
        }

        try {
            $user->notifyNow(new NtfyTest);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Sent! Check your phone.')]);
        } catch (Throwable $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Could not reach the notification server.')]);
        }

        return to_route('profile.edit');
    }
}
