<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'order_type',
        'customer_name',
        'notes',
        'payment_method',
        'gcash_reference_number',
        'gcash_proof',
        'total',
        'amount_received',
        'change',
        'status',
    ];

    protected $appends = ['gcash_proof_url'];

    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
            'total' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function getGcashProofUrlAttribute(): ?string
    {
        return $this->gcash_proof ? Storage::disk('public')->url($this->gcash_proof) : null;
    }
}