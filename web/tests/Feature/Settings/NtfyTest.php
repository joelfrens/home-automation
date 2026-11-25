<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('a user can save their ntfy topic', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'ntfy_topic' => 'k3J9xQ2mP8vT1rW5'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->ntfy_topic)->toBe('k3J9xQ2mP8vT1rW5');
});

test('short or odd topics are refused', function (string $topic) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'ntfy_topic' => $topic])
        ->assertSessionHasErrors('ntfy_topic');
})->with(['too short' => ['family'], 'spaces' => ['our family topic 123'], 'slash' => ['abc/def/ghi/jkl']]);

test('the test notification goes to the users own topic', function () {
    Http::fake();
    config()->set('reminders.ntfy.server', 'https://ntfy.example');
    $user = User::factory()->create(['ntfy_topic' => 'k3J9xQ2mP8vT1rW5']);

    $this->actingAs($user)->post(route('profile.ntfy-test'))->assertRedirect(route('profile.edit'));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://ntfy.example'
        && $request['topic'] === 'k3J9xQ2mP8vT1rW5');
});

test('without a topic nothing is sent', function () {
    Http::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.ntfy-test'))->assertRedirect(route('profile.edit'));

    Http::assertNothingSent();
});
