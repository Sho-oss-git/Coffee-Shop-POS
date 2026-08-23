<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'day_of_week',
        'expected_time_in',
        'expected_time_out',
        'is_day_off',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'expected_time_in' => 'datetime:H:i',
            'expected_time_out' => 'datetime:H:i',
            'is_day_off' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}