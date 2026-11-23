<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

/** The events the family kept on the review screen (after the AI read a letter). */
class ReviewedEventsRequest extends FormRequest
{
    use EventRules;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:30'],
            ...$this->eventRules('events.*.'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['events.required' => 'Keep at least one event, or press Cancel.'];
    }
}
