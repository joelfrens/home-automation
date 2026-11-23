<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

/** One event: manual "Add event" and editing. */
class EventRequest extends FormRequest
{
    use EventRules;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->eventRules();
    }
}
