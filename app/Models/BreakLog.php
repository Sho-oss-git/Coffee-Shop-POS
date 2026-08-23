<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'break_started_at',
        'break_ended_at',
    ];

    protected function casts(): array
    {
        return [
            'break_started_at' => 'datetime',
            'break_ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDurationInMinutesAttribute(): ?int
    {
        if (! $this->break_ended_at) {
            return null;
        }

        return $this->break_started_at->diffInMinutes($this->break_ended_at);
    }
}