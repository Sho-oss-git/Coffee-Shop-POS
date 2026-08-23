<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class SetEmployeeStatusOnLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // Only auto-track cashiers and managers — admins aren't "on shift"
        if (in_array($user->role->value ?? $user->role, ['cashier', 'manager'])) {
            $user->update(['status' => 'working']);

            $user->employeeLogs()->create([
                'action' => 'login',
                'logged_at' => now(),
            ]);
        }
    }
}