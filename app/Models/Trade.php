<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'coin',
        'capital',
        'risk_percent',
        'risk_amount',
        'stop_loss_percent',
        'position_size',
        'entry_price',
        'take_profit_price',
        'result',
        'profit_loss',
    ];

    protected $casts = [
        'capital' => 'decimal:2',
        'risk_percent' => 'decimal:2',
        'risk_amount' => 'decimal:2',
        'stop_loss_percent' => 'decimal:2',
        'position_size' => 'decimal:6',
        'entry_price' => 'decimal:8',
        'take_profit_price' => 'decimal:8',
        'profit_loss' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
