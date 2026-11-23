<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Joelfrens\HomeAutomation\Reminders\Models\Event;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 12:00', 'UTC'));
    $this->user = User::factory()->create();
});

function eventInput(array $overrides = []): array
{
    return [
        'title' => 'Science museum trip',
        'child_id' => null,
        'date' => '2026-12-04',
        'start_time' => '08:45',
        'end_time' => null,
        'all_day' => false,
        'location' => null,
        'needs' => 'Packed lunch',
        'notes' => null,
        'reminders' => [['days_before' => 1, 'time' => '19:00']],
        ...$overrides,
    ];
}

test('guests cannot see school events', function () {
    $this->get(route('school.index'))->assertRedirect(route('login'));
});

test('the school page lists upcoming events and children', function () {
    $emma = Child::create(['name' => 'Emma', 'colour' => 'rose']);
    $this->actingAs($this->user)->post(route('school.events.store'), eventInput(['child_id' => $emma->id]));

    $this->actingAs($this->user)
        ->get(route('school.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('school/index')
            ->has('events', 1)
            ->where('events.0.title', 'Science museum trip')
            ->where('events.0.child.name', 'Emma')
            ->where('events.0.startTime', '08:45')
            ->has('children', 1));
});

test('an event can be added by hand with its reminders', function () {
    $this->actingAs($this->user)
        ->post(route('school.events.store'), eventInput())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('school.index'));

    $event = Event::sole();
    expect($event->created_by)->toBe($this->user->id)
        ->and($event->starts_at->toIso8601String())->toBe('2026-12-04T08:45:00+00:00')
        ->and(Reminder::sole()->remind_at->toIso8601String())->toBe('2026-12-03T19:00:00+00:00');
});

test('events are validated', function () {
    $this->actingAs($this->user)
        ->post(route('school.events.store'), eventInput([
            'title' => '', 'date' => '4th Dec', 'start_time' => '8.45',
            'child_id' => 999, 'reminders' => [['days_before' => 99, 'time' => '7pm']],
        ]))
        ->assertSessionHasErrors(['title', 'date', 'start_time', 'child_id', 'reminders.0.days_before', 'reminders.0.time']);

    expect(Event::count())->toBe(0);
});

test('an event can be edited and deleted', function () {
    $this->actingAs($this->user)->post(route('school.events.store'), eventInput());
    $event = Event::sole();

    $this->actingAs($this->user)->get(route('school.events.edit', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('school/edit')->where('event.title', 'Science museum trip'));

    $this->actingAs($this->user)
        ->put(route('school.events.update', $event), eventInput(['title' => 'Museum trip', 'reminders' => []]))
        ->assertRedirect(route('school.index'));
    expect($event->fresh()->title)->toBe('Museum trip')
        ->and(Reminder::count())->toBe(0);

    $this->actingAs($this->user)->delete(route('school.events.destroy', $event))->assertRedirect(route('school.index'));
    expect(Event::count())->toBe(0);
});

test('children can be added and removed, their events stay', function () {
    $this->actingAs($this->user)
        ->post(route('school.children.store'), ['name' => 'Leo', 'colour' => 'emerald'])
        ->assertSessionHasNoErrors();
    $leo = Child::sole();

    $this->actingAs($this->user)
        ->post(route('school.children.store'), ['name' => 'Leo', 'colour' => 'pink'])
        ->assertSessionHasErrors(['name', 'colour']);

    $this->actingAs($this->user)->post(route('school.events.store'), eventInput(['child_id' => $leo->id]));
    $this->actingAs($this->user)->delete(route('school.children.destroy', $leo));

    expect(Child::count())->toBe(0)
        ->and(Event::sole()->child_id)->toBeNull();
});
