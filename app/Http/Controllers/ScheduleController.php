<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    private const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function edit(User $user): Response
    {
        $user->load('schedules');

        $schedule = collect(self::DAYS)->map(function (string $label, int $dayOfWeek) use ($user) {
            $existing = $user->schedules->firstWhere('day_of_week', $dayOfWeek);

            return [
                'day_of_week' => $dayOfWeek,
                'day_label' => $label,
                'expected_time_in' => $existing?->expected_time_in?->format('H:i'),
                'expected_time_out' => $existing?->expected_time_out?->format('H:i'),
                'is_day_off' => $existing?->is_day_off ?? false,
            ];
        })->values();

        return Inertia::render('users/Schedule', [
            'employee' => ['id' => $user->id, 'name' => $user->name],
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'schedule' => ['required', 'array', 'size:7'],
            'schedule.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedule.*.is_day_off' => ['required', 'boolean'],
            'schedule.*.expected_time_in' => ['nullable', 'date_format:H:i'],
            'schedule.*.expected_time_out' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($validated['schedule'] as $day) {
            $user->schedules()->updateOrCreate(
                ['day_of_week' => $day['day_of_week']],
                [
                    'is_day_off' => $day['is_day_off'],
                    'expected_time_in' => $day['is_day_off'] ? null : ($day['expected_time_in'] ?? null),
                    'expected_time_out' => $day['is_day_off'] ? null : ($day['expected_time_out'] ?? null),
                ]
            );
        }

        return redirect()->route('user-management.index')
            ->with('success', 'Schedule updated successfully.');
    }
}