<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class ClockStatusController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(EmployeeStatus::class)],
        ]);

        $user = $request->user();
        $newStatus = EmployeeStatus::from($validated['status']);
        $currentStatus = $user->status instanceof EmployeeStatus
            ? $user->status
            : EmployeeStatus::from($user->status);

        // Papasok sa break — buksan ang bagong BreakLog entry
        if ($newStatus === EmployeeStatus::Break && $currentStatus !== EmployeeStatus::Break) {
            $user->breakLogs()->create([
                'break_started_at' => now(),
            ]);

            $user->employeeLogs()->create([
                'action' => 'break_start',
                'logged_at' => now(),
            ]);
        }

        // Lalabas sa break (papunta man sa Working o OffDuty) — isara ang bukas na BreakLog
        if ($currentStatus === EmployeeStatus::Break && $newStatus !== EmployeeStatus::Break) {
            $user->breakLogs()
                ->whereNull('break_ended_at')
                ->latest('break_started_at')
                ->first()
                ?->update(['break_ended_at' => now()]);

            $user->employeeLogs()->create([
                'action' => 'break_end',
                'logged_at' => now(),
            ]);
        }

        $user->update(['status' => $newStatus]);

        return back()->with('success', 'Status updated.');
    }
}