<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FamilyMemberStoreRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Family: admins add and remove the household's accounts.
 * Registration is turned off, so this is how a partner (or anyone else) gets a login.
 */
class FamilyController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/family', [
            'members' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_admin', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isAdmin' => $user->is_admin,
                    'isYou' => $user->is($request->user()),
                ]),
        ]);
    }

    public function store(FamilyMemberStoreRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        // Not mass-assignable on purpose; there is no email server, so the address counts as verified.
        $user->forceFill([
            'is_admin' => $request->boolean('is_admin'),
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name can now log in.', ['name' => $user->name])]);

        return to_route('family.index');
    }

    /**
     * Make someone an admin, or take admin away (never from the last admin).
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages([
                'member' => __('The household needs at least one admin.'),
            ]);
        }

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        // Took admin away from yourself: this page is admin-only, so go back to your profile.
        if ($user->is($request->user()) && ! $user->is_admin) {
            return to_route('profile.edit');
        }

        return to_route('family.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Removing yourself goes through Settings → Profile → Delete account (asks for your password).
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'member' => __('You can\'t remove yourself here.'),
            ]);
        }

        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages([
                'member' => __('The household needs at least one admin.'),
            ]);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was removed.', ['name' => $user->name])]);

        return to_route('family.index');
    }
}
