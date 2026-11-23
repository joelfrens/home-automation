<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\EventRequest;
use App\Http\Requests\School\ReadLetterRequest;
use App\Http\Requests\School\ReviewedEventsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Joelfrens\HomeAutomation\Reminders\EventExtractor;
use Joelfrens\HomeAutomation\Reminders\EventPlanner;
use Joelfrens\HomeAutomation\Reminders\Exceptions\ExtractionFailed;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Joelfrens\HomeAutomation\Reminders\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * School & family reminders. The logic lives in the reminders package (packages/reminders);
 * this controller only connects pages to it.
 *
 * Flow for a letter: /school/new (upload or paste) -> read() asks the AI -> /school/review
 * (the family checks and edits) -> storeReviewed() saves. The AI never saves anything itself.
 *
 * @phpstan-import-type EventInput from EventPlanner
 */
class SchoolController extends Controller
{
    private const REVIEW_KEY = 'reminders.review';

    public function __construct(private readonly EventPlanner $planner) {}

    public function index(): Response
    {
        return Inertia::render('school/index', [
            'events' => $this->planner->upcoming()->map(fn (Event $e) => $this->planner->present($e))->values(),
            'children' => $this->children(),
            'colours' => Child::COLOURS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('school/new', [
            'children' => $this->children(),
        ]);
    }

    public function read(ReadLetterRequest $request, EventExtractor $extractor): RedirectResponse
    {
        $path = $request->file('file')?->store(
            config('reminders.storage.path').'/'.now()->format('Y/m'),
            config('reminders.storage.disk'),
        );

        try {
            $proposals = $extractor->extract($path ?: null, $request->validated('text'));
        } catch (ExtractionFailed $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if ($proposals === []) {
            return back()->withErrors(['file' => __('No events with a date were found. You can add one by hand below.')]);
        }

        $request->session()->put(self::REVIEW_KEY, ['source_path' => $path ?: null, 'proposals' => $proposals]);

        return to_route('school.review');
    }

    public function review(Request $request): Response|RedirectResponse
    {
        $review = $request->session()->get(self::REVIEW_KEY);

        if (! $review) {
            return to_route('school.create');
        }

        return Inertia::render('school/review', [
            'proposals' => $review['proposals'],
            'children' => $this->children(),
        ]);
    }

    public function storeReviewed(ReviewedEventsRequest $request): RedirectResponse
    {
        $sourcePath = $request->session()->get(self::REVIEW_KEY.'.source_path');

        /** @var list<EventInput> $events validated by ReviewedEventsRequest */
        $events = $request->validated('events');

        foreach ($events as $event) {
            $this->planner->create([...$event, 'source_path' => $sourcePath], $request->user()->id);
        }

        $request->session()->forget(self::REVIEW_KEY);
        $count = count($events);
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} 1 event saved.|[2,*] :count events saved.', $count)]);

        return to_route('school.index');
    }

    public function cancelReview(Request $request): RedirectResponse
    {
        $request->session()->forget(self::REVIEW_KEY);

        return to_route('school.index');
    }

    public function store(EventRequest $request): RedirectResponse
    {
        /** @var EventInput $input validated by EventRequest */
        $input = $request->validated();
        $this->planner->create($input, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event saved.')]);

        return to_route('school.index');
    }

    public function edit(Event $event): Response
    {
        return Inertia::render('school/edit', [
            'event' => $this->planner->present($event->load(['child', 'reminders'])),
            'children' => $this->children(),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        /** @var EventInput $input validated by EventRequest */
        $input = $request->validated();
        $this->planner->update($event, $input);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return to_route('school.index');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted.')]);

        return to_route('school.index');
    }

    /** The letter/screenshot an event came from (private file, logged-in users only). */
    public function source(Event $event): StreamedResponse
    {
        $disk = Storage::disk(config('reminders.storage.disk'));

        abort_unless($event->source_path && $disk->exists($event->source_path), 404);

        return $disk->response($event->source_path);
    }

    /** @return list<array{id: int, name: string, colour: string}> */
    private function children(): array
    {
        return array_values(Child::query()->orderBy('name')->get(['id', 'name', 'colour'])
            ->map(fn (Child $c) => ['id' => $c->id, 'name' => $c->name, 'colour' => $c->colour])
            ->all());
    }
}
