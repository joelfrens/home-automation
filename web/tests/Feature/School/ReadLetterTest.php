<?php

use App\Models\AiUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Joelfrens\HomeAutomation\Reminders\Ai\ExtractEvents;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Joelfrens\HomeAutomation\Reminders\Models\Event;

/*
 * The AI is always faked: no test ever calls Claude (no cost, no network, same answer every time).
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 12:00', 'UTC'));
    $this->user = User::factory()->create();
    Storage::fake('local');
});

function aiFinds(array ...$events): void
{
    ExtractEvents::fake([['events' => array_map(fn (array $e) => [
        'title' => 'Science museum trip', 'child' => null, 'date' => '2026-12-04',
        'start_time' => '08:45', 'end_time' => null, 'all_day' => false, 'location' => null,
        'needs' => 'Packed lunch, £5', 'notes' => null,
        'reminders' => [['days_before' => 7, 'time' => '19:00'], ['days_before' => 1, 'time' => '19:00']],
        ...$e,
    ], $events)]]);
}

test('pasted text is read by the AI and shown for review, nothing saved yet', function () {
    Child::create(['name' => 'Emma']);
    aiFinds(['child' => 'Emma'], ['title' => 'Christmas jumper day', 'date' => '2026-12-11', 'start_time' => null, 'all_day' => true]);

    $this->actingAs($this->user)
        ->post(route('school.read'), ['text' => 'Year 3 trip to the Science Museum on Friday 4 December...'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('school.review'));

    expect(Event::count())->toBe(0);

    $this->actingAs($this->user)
        ->get(route('school.review'))
        ->assertInertia(fn ($page) => $page
            ->component('school/review')
            ->has('proposals', 2)
            ->where('proposals.0.title', 'Science museum trip')
            ->where('proposals.0.child_id', Child::sole()->id)
            ->where('proposals.1.all_day', true));
});

test('a PDF is stored privately and remembered as the source of the saved events', function () {
    aiFinds([]);

    $this->actingAs($this->user)
        ->post(route('school.read'), ['file' => UploadedFile::fake()->create('letter.pdf', 200, 'application/pdf')])
        ->assertRedirect(route('school.review'));

    $stored = Storage::disk('local')->allFiles('reminders');
    expect($stored)->toHaveCount(1);

    $proposals = session('reminders.review.proposals');
    $this->actingAs($this->user)
        ->post(route('school.review.store'), ['events' => $proposals])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('school.index'));

    $event = Event::sole();
    expect($event->source_path)->toBe($stored[0])
        ->and($event->reminders)->toHaveCount(2)
        ->and(session('reminders.review'))->toBeNull();

    $this->actingAs($this->user)->get(route('school.events.source', $event))->assertOk();
});

test('the family can drop and fix proposals before saving', function () {
    aiFinds([], ['title' => 'Wrong one']);
    $this->actingAs($this->user)->post(route('school.read'), ['text' => 'letter']);

    $kept = [session('reminders.review.proposals')[0]];
    $kept[0]['date'] = '2026-12-05';

    $this->actingAs($this->user)->post(route('school.review.store'), ['events' => $kept]);

    expect(Event::sole()->starts_at->toDateString())->toBe('2026-12-05');
});

test('the review page needs something to review', function () {
    $this->actingAs($this->user)->get(route('school.review'))->assertRedirect(route('school.create'));
});

test('cancelling a review forgets the proposals', function () {
    aiFinds([]);
    $this->actingAs($this->user)->post(route('school.read'), ['text' => 'letter']);

    $this->actingAs($this->user)->delete(route('school.review.cancel'))->assertRedirect(route('school.index'));

    expect(session('reminders.review'))->toBeNull();
});

test('a file or some text is required', function () {
    $this->actingAs($this->user)->post(route('school.read'), [])->assertSessionHasErrors(['file', 'text']);
});

test('iPhone HEIC photos and other files are refused with a helpful message', function () {
    $this->actingAs($this->user)
        ->post(route('school.read'), ['file' => UploadedFile::fake()->create('IMG_0001.heic', 500, 'image/heic')])
        ->assertSessionHasErrors(['file' => 'Use a PDF, PNG, JPEG or WebP. iPhone photos (HEIC): take a screenshot of the photo instead.']);

    ExtractEvents::assertNeverPrompted();
});

test('images over 5 MB are refused', function () {
    $this->actingAs($this->user)
        ->post(route('school.read'), ['file' => UploadedFile::fake()->create('huge.png', 6 * 1024, 'image/png')])
        ->assertSessionHasErrors('file');
});

test('when the AI is unavailable the family is told to add it by hand', function () {
    ExtractEvents::fake(fn () => throw new RuntimeException('overloaded'));

    $this->actingAs($this->user)
        ->post(route('school.read'), ['text' => 'letter'])
        ->assertSessionHasErrors('file');

    expect(session('reminders.review'))->toBeNull();
});

test('a letter without dates gives a clear message', function () {
    ExtractEvents::fake([['events' => []]]);

    $this->actingAs($this->user)
        ->post(route('school.read'), ['text' => 'Thank you for a lovely term!'])
        ->assertSessionHasErrors(['file' => 'No events with a date were found. You can add one by hand below.']);
});

test('every AI call is logged for the monthly cost', function () {
    aiFinds([]);

    $this->actingAs($this->user)->post(route('school.read'), ['text' => 'letter']);

    $usage = AiUsage::sole();
    expect($usage->agent)->toBe('ExtractEvents')
        ->and($usage->model)->toBe('claude-opus-5-5');
});

test('AI cost is worked out from the price list', function () {
    // 10,000 tokens in, 2,000 out on Opus 5.5: 10k × $4/M + 2k × $20/M = $0.08
    expect(AiUsage::costFor('claude-opus-5-5', 10_000, 2_000))->toBe(0.08)
        ->and(AiUsage::costFor('qwen3:4b', 10_000, 2_000))->toBe(0.0);
});
