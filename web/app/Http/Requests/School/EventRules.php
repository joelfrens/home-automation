<?php

namespace App\Http\Requests\School;

/**
 * Validation for one event (used for a single event and for each event on the review screen).
 * Same shape as the reminders package's EventPlanner input.
 */
trait EventRules
{
    /** @return array<string, list<string>> */
    protected function eventRules(string $prefix = ''): array
    {
        return [
            "{$prefix}title" => ['required', 'string', 'max:200'],
            "{$prefix}child_id" => ['nullable', 'integer', 'exists:children,id'],
            "{$prefix}date" => ['required', 'date_format:Y-m-d'],
            "{$prefix}all_day" => ['boolean'],
            "{$prefix}start_time" => ['nullable', 'date_format:H:i'],
            "{$prefix}end_time" => ['nullable', 'date_format:H:i'],
            "{$prefix}location" => ['nullable', 'string', 'max:200'],
            "{$prefix}needs" => ['nullable', 'string', 'max:500'],
            "{$prefix}notes" => ['nullable', 'string', 'max:1000'],
            "{$prefix}reminders" => ['array', 'max:6'],
            "{$prefix}reminders.*.days_before" => ['required', 'integer', 'min:0', 'max:60'],
            "{$prefix}reminders.*.time" => ['required', 'date_format:H:i'],
        ];
    }
}
