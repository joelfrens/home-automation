<?php

use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;

/*
 * The browser asks POST /broadcasting/auth before Reverb lets it listen to a private channel.
 * Tests normally use the "null" broadcaster, which approves nothing, so switch to Reverb
 * (with made-up keys; nothing is sent anywhere) and register the channels on it.
 */
beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');

    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
});

function askToListen(string $channel)
{
    return test()->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

test('a logged-in family member may listen to live readings', function () {
    $this->actingAs(User::factory()->create());

    askToListen('private-readings')
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test('guests may not listen to live readings', function () {
    askToListen('private-readings')->assertForbidden();
});

test('a user may not listen to someone else\'s personal channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user);

    askToListen("private-App.Models.User.{$user->id}")->assertOk();
    askToListen("private-App.Models.User.{$other->id}")->assertForbidden();
});
