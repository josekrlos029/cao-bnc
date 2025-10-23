<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fiat',
        'asset',
        'asset_rate',
        'operation',
        'min_limit',
        'max_limit',
        'payment_methods',
        'ad_number',
        'min_positions',
        'max_positions',
        'min_price',
        'max_price',
        'min_usd_diff',
        'max_usd_diff',
        'profile',
        'increment',
        'difference',
        'max_price_enabled',
        'max_price_limit',
        'min_volume_enabled',
        'min_volume',
        'min_limit_enabled',
        'min_limit_threshold',
        'is_active',
        'last_checked_at',
    ];

    protected $casts = [
        'payment_methods' => 'array',
        'asset_rate' => 'decimal:8',
        'min_limit' => 'decimal:2',
        'max_limit' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'min_usd_diff' => 'decimal:2',
        'max_usd_diff' => 'decimal:2',
        'increment' => 'decimal:2',
        'difference' => 'decimal:2',
        'max_price_limit' => 'decimal:2',
        'min_volume' => 'decimal:2',
        'min_limit_threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'max_price_enabled' => 'boolean',
        'min_volume_enabled' => 'boolean',
        'min_limit_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(BotActionLog::class);
    }

    public function pendingActions(): HasMany
    {
        return $this->hasMany(BotActionLog::class)->where('status', 'pending_approval');
    }
}