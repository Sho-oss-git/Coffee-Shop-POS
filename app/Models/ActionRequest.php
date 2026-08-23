<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ActionRequest extends Model
{
    protected $fillable = [
        'requested_by',
        'reviewed_by',
        'type',
        'target_type',
        'target_id',
        'payload',
        'reason',
        'status',
        'review_note',
        'reviewed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'payload' => 'array',
            'target_id' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
