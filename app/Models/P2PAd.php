<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class P2PAd extends Model
{
    use HasFactory;

    protected $table = 'p2p_ads';

    protected $fillable = [
        'ad_number',
        'fiat',
        'asset',
        'price',
        'available_amount',
        'min_limit',
        'max_limit',
        'payment_methods',
        'advertiser_id',
        'advertiser_nickname',
        'advertiser_month_finish_rate',
        'advertiser_month_order_count',
        'advertiser_month_finish_count',
        'status',
        'position',
        'usd_difference',
        'binance_updated_at',
    ];

    protected $casts = [
        'payment_methods' => 'array',
        'price' => 'decimal:2',
        'available_amount' => 'decimal:8',
        'min_limit' => 'decimal:2',
        'max_limit' => 'decimal:2',
        'usd_difference' => 'decimal:2',
        'binance_updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByAsset($query, $asset, $fiat)
    {
        return $query->where('asset', $asset)->where('fiat', $fiat);
    }

    public function scopeByPosition($query, $minPosition = null, $maxPosition = null)
    {
        if ($minPosition !== null) {
            $query->where('position', '>=', $minPosition);
        }
        if ($maxPosition !== null) {
            $query->where('position', '<=', $maxPosition);
        }
        return $query;
    }
}