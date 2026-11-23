<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Joelfrens\HomeAutomation\Reminders\Ai\ExtractEvents;
use Joelfrens\HomeAutomation\Reminders\EventExtractor;
use Joelfrens\HomeAutomation\Reminders\Exceptions\ExtractionFailed;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;

/** The AI is always faked here: these tests check what we do with its answer. */
class EventExtractorTest extends TestCase
{
    private function extract(?string $path = null, ?string $text = 'letter text'): array
    {
        return $this->app->make(EventExtractor::class)
            ->extract($path, $text, CarbonImmutable::parse('2026-11-23 12:00', 'UTC'));
    }

    private function aiEvent(array $overrides = []): array
    {
        return [
            'title' => 'Science museum trip', 'child' => null, 'date' => '2026-12-04',
            'start_time' => '08:45', 'end_time' => '15:00', 'all_day' => false,
            'location' => 'Science Museum', 'needs' => 'Packed lunch', 'notes' => null,
            'reminders' => [['days_before' => 7, 'time' => '19:00'], ['days_before' => 1, 'time' => '19:00']],
            ...$overrides,
        ];
    }

    public function test_the_answer_becomes_clean_proposals_matched_to_children()
    {
        $emma = Child::create(['name' => 'Emma']);
        ExtractEvents::fake([['events' => [$this->aiEvent(['child' => 'emma'])]]]);

        $proposals = $this->extract();

        $this->assertSame([[
            'title' => 'Science museum trip', 'child_id' => $emma->id, 'date' => '2026-12-04',
            'start_time' => '08:45', 'end_time' => '15:00', 'all_day' => false,
            'location' => 'Science Museum', 'needs' => 'Packed lunch', 'notes' => null,
            'reminders' => [['days_before' => 7, 'time' => '19:00'], ['days_before' => 1, 'time' => '19:00']],
        ]], $proposals);
    }

    public function test_the_prompt_contains_today_and_the_childrens_names()
    {
        Child::create(['name' => 'Emma']);
        Child::create(['name' => 'Leo']);
        ExtractEvents::fake([['events' => []]]);

        $this->extract(text: 'PE on Tuesday');

        ExtractEvents::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Monday 23 November 2026')
            && $prompt->contains('Emma, Leo')
            && $prompt->contains('PE on Tuesday'));
    }

    public function test_impossible_or_past_dates_and_bad_times_are_dropped()
    {
        ExtractEvents::fake([['events' => [
            $this->aiEvent(['title' => 'No such day', 'date' => '2026-02-30']),
            $this->aiEvent(['title' => 'Already over', 'date' => '2026-11-20']),
            $this->aiEvent(['title' => '', 'date' => '2026-12-01']),
            $this->aiEvent(['title' => 'Odd time', 'date' => '2026-12-02', 'start_time' => '8.45am',
                'reminders' => [['days_before' => 1, 'time' => '7pm'], ['days_before' => 1, 'time' => '19:00'], ['days_before' => 1, 'time' => '19:00']]]),
        ]]]);

        $proposals = $this->extract();

        $this->assertCount(1, $proposals);
        $this->assertSame('Odd time', $proposals[0]['title']);
        $this->assertTrue($proposals[0]['all_day']);       // time unreadable -> all day
        $this->assertNull($proposals[0]['start_time']);
        $this->assertSame([['days_before' => 1, 'time' => '19:00']], $proposals[0]['reminders']);
    }

    public function test_an_unknown_child_name_is_not_guessed()
    {
        Child::create(['name' => 'Emma']);
        ExtractEvents::fake([['events' => [$this->aiEvent(['child' => 'Olivia'])]]]);

        $this->assertNull($this->extract()[0]['child_id']);
    }

    public function test_a_pdf_is_attached_as_a_document()
    {
        Storage::fake('local');
        Storage::disk('local')->put('reminders/letter.pdf', "%PDF-1.4\n%fake\n");
        ExtractEvents::fake([['events' => []]]);

        $this->extract('reminders/letter.pdf', null);

        ExtractEvents::assertPrompted(fn (AgentPrompt $prompt) => $prompt->attachments->count() === 1
            && $prompt->attachments->first() instanceof Document);
    }

    public function test_an_ai_failure_becomes_a_friendly_error()
    {
        ExtractEvents::fake(fn () => throw new RuntimeException('overloaded'));

        $this->expectException(ExtractionFailed::class);
        $this->expectExceptionMessage('add the event by hand');

        $this->extract();
    }
}
