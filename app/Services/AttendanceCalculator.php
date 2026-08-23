<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * Compute the full attendance breakdown for one employee on one date.
     *
     * Assumes $user->schedules is already eager-loaded.
     */
    public function calculateForDate(User $user, Carbon $date): array
    {
        $isFutureDate = $date->copy()->startOfDay()->gt(Carbon::today());

        $dayOfWeek = $date->dayOfWeek; // 0 (Sunday) .. 6 (Saturday)

        $schedule = $user->schedules->firstWhere('day_of_week', $dayOfWeek);

        $logsForDay = $user->employeeLogs()
            ->whereDate('logged_at', $date->toDateString())
            ->orderBy('logged_at')
            ->get();

        $firstLogin = $logsForDay->firstWhere('action', 'login');
        $lastLogout = $logsForDay->where('action', 'logout')->last();

        $breakMinutes = $user->breakLogs()
            ->whereDate('break_started_at', $date->toDateString())
            ->whereNotNull('break_ended_at')
            ->get()
            ->sum(fn ($log) => $log->duration_in_minutes);

        // "May schedule" lang kung Working Day AT parehong naka-fill ang time in/out.
        // Kung Working Day pero walang laman ang oras, hindi ito totoong schedule.
        $hasSchedule = $schedule
            && ! $schedule->is_day_off
            && $schedule->expected_time_in
            && $schedule->expected_time_out;

        $result = [
            'date' => $date->toDateString(),
            'day_label' => $date->format('D, M d'),
            'is_day_off' => $schedule?->is_day_off ?? false,
            'expected_time_in' => $hasSchedule ? $schedule->expected_time_in->format('h:i A') : null,
            'expected_time_out' => $hasSchedule ? $schedule->expected_time_out->format('h:i A') : null,
            'actual_time_in' => $firstLogin?->logged_at->format('h:i A'),
            'actual_time_out' => $lastLogout?->logged_at->format('h:i A'),
            'late_minutes' => null,
            'undertime_minutes' => null,
            'overtime_minutes' => null,
            'break_minutes' => $breakMinutes > 0 ? $breakMinutes : null,
            'total_work_hours' => null,
            'status' => null,
        ];

        // Day off — walang kailangang i-compute
        if ($result['is_day_off']) {
            $result['status'] = 'Day Off';

            return $result;
        }

        // Hindi pa dumarating ang araw na ito — huwag pang markahan ng Absent
        if ($isFutureDate) {
            $result['status'] = 'Upcoming';

            return $result;
        }

        // Walang naka-assign na schedule, o naka-save bilang Working Day pero
        // walang na-fill na oras (hindi totoong schedule)
        if (! $hasSchedule) {
            $result['status'] = $firstLogin ? 'No Schedule' : ($schedule ? 'Rest Day' : 'No Schedule');

            return $result;
        }

        // Walang time in — wala siyang pumasok
        if (! $firstLogin) {
            $result['status'] = 'Absent';

            return $result;
        }

        $actualIn = $firstLogin->logged_at;

        $expectedIn = $date->copy()->setTimeFrom($schedule->expected_time_in);

        if ($actualIn->gt($expectedIn)) {
            $result['late_minutes'] = $expectedIn->diffInMinutes($actualIn);
        }

        if ($lastLogout) {
            $expectedOut = $date->copy()->setTimeFrom($schedule->expected_time_out);
            $actualOut = $lastLogout->logged_at;

            if ($actualOut->lt($expectedOut)) {
                $result['undertime_minutes'] = $actualOut->diffInMinutes($expectedOut);
            } elseif ($actualOut->gt($expectedOut)) {
                $result['overtime_minutes'] = $expectedOut->diffInMinutes($actualOut);
            }

            $grossMinutes = $actualIn->diffInMinutes($actualOut);
            $result['total_work_hours'] = round(($grossMinutes - $breakMinutes) / 60, 2);
        }

        $result['status'] = $lastLogout ? 'Complete' : 'Still Working';

        return $result;
    }
}