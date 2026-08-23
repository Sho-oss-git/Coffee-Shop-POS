<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class SetEmployeeStatusOnLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        if (! in_array($user->role->value ?? $user->role, ['cashier', 'manager'])) {
            return;
        }

        // Kung naka-break siya bago mag-logout, isara muna ang open break log
        // at i-log ang break_end bago ang logout, para tama ang pagkakasunod-sunod.
        if ($user->status === 'break') {
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

        $user->update(['status' => 'off_duty']);

        $user->employeeLogs()->create([
            'action' => 'logout',
            'logged_at' => now(),
        ]);
    }
}