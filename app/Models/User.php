<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isCashier(): bool
    {
        return $this->role === UserRole::Cashier;
    }

    /** Check against one or more roles, e.g. $user->hasRole('admin', 'manager') */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles, true);
    }

    /**
     * All break in/out records for this user.
     * Used for the quick "Last Break" preview on the User Management table.
     */
    public function breakLogs(): HasMany
    {
        return $this->hasMany(BreakLog::class);
    }

    /**
     * Most recent break record (whether closed or still ongoing).
     */
    public function latestBreakLog(): HasOne
    {
        return $this->hasOne(BreakLog::class)->latestOfMany('break_started_at');
    }

    /**
     * Full activity timeline: login, logout, break_start, break_end.
     * Used to power the "View Records" modal in User Management.
     */
    public function employeeLogs(): HasMany
    {
        return $this->hasMany(EmployeeLog::class);
    }

    /**
     * Weekly schedule — one row per day of week (0=Sunday..6=Saturday).
     * Used by AttendanceCalculator to compute Late/Undertime/Overtime.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}