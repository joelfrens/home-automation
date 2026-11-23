<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('guests are sent to the login page', function () {
    $this->get(route('family.index'))->assertRedirect(route('login'));
});

test('family members who are not admins cannot open the page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('family.index'))
        ->assertForbidden();
});

test('admins see every family member', function () {
    $admin = User::factory()->admin()->create(['name' => 'Joel']);
    User::factory()->create(['name' => 'Partner']);

    $this->actingAs($admin)
        ->get(route('family.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/family')
            ->has('members', 2)
            ->where('members.0.name', 'Joel')
            ->where('members.0.isYou', true)
            ->where('members.0.isAdmin', true)
            ->where('members.1.isYou', false));
});

test('an admin can add a family member who can then log in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('family.store'), [
            'name' => 'Partner',
            'email' => 'Partner@Example.com',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('family.index'));

    $partner = User::where('email', 'partner@example.com')->sole();
    expect($partner->is_admin)->toBeFalse()
        ->and($partner->email_verified_at)->not->toBeNull();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => 'partner@example.com', 'password' => 'temporary-password']);
    $this->assertAuthenticatedAs($partner);
});

test('the admin checkbox makes the new member an admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('family.store'), [
            'name' => 'Partner',
            'email' => 'partner@example.com',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
            'is_admin' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'partner@example.com')->sole()->is_admin)->toBeTrue();
});

test('a member needs a unique email and a confirmed password', function () {
    $admin = User::factory()->admin()->create(['email' => 'joel@example.com']);

    $this->actingAs($admin)
        ->post(route('family.store'), [
            'name' => 'Someone',
            'email' => 'joel@example.com',
            'password' => 'temporary-password',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors(['email', 'password']);
});

test('non-admins cannot add or remove members', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('family.store'), ['name' => 'x'])->assertForbidden();
    $this->actingAs($user)->delete(route('family.destroy', $other))->assertForbidden();
    $this->actingAs($user)->patch(route('family.toggle-admin', $user))->assertForbidden();

    expect($other->fresh())->not->toBeNull()
        ->and($user->fresh()->is_admin)->toBeFalse();
});

test('an admin can remove a family member', function () {
    $admin = User::factory()->admin()->create();
    $partner = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('family.destroy', $partner))
        ->assertRedirect(route('family.index'));

    expect($partner->fresh())->toBeNull();
});

test('an admin cannot remove themselves here', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('family.destroy', $admin))
        ->assertSessionHasErrors('member');

    expect($admin->fresh())->not->toBeNull();
});

test('an admin can make someone an admin and take it away again', function () {
    $admin = User::factory()->admin()->create();
    $partner = User::factory()->create();

    $this->actingAs($admin)->patch(route('family.toggle-admin', $partner));
    expect($partner->fresh()->is_admin)->toBeTrue();

    $this->actingAs($admin)->patch(route('family.toggle-admin', $partner));
    expect($partner->fresh()->is_admin)->toBeFalse();
});

test('the last admin cannot stop being an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('family.toggle-admin', $admin))
        ->assertSessionHasErrors('member');

    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('an admin who gives up admin is sent back to their profile', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('family.toggle-admin', $admin))
        ->assertRedirect(route('profile.edit'));

    expect($admin->fresh()->is_admin)->toBeFalse();
});

test('the last admin cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($admin->fresh())->not->toBeNull();
});

test('the seeder creates the first account as an admin', function () {
    config()->set('app.admin', ['name' => 'Joel', 'email' => 'joel@example.com', 'password' => 'secret-password']);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'joel@example.com')->sole()->is_admin)->toBeTrue();
});
