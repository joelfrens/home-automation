<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Joelfrens\HomeAutomation\Reminders\Models\Child;

/** The children events can belong to (names help the AI, colours help the page). */
class ChildController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('children', 'name')],
            'colour' => ['required', Rule::in(Child::COLOURS)],
        ]);

        Child::create($data);

        return to_route('school.index');
    }

    /** Their events stay, just without a child. */
    public function destroy(Child $child): RedirectResponse
    {
        $child->delete();

        return to_route('school.index');
    }
}
