<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'ad_number',
        'fiat',
        'asset',
        'amount',
        'price',
        'total',
        'trade_type',
        'status',
        'buyer_id',
        'buyer_nickname',
        'seller_id',
        'seller_nickname',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('trade_type', $type);
    }

    public function scopeByAsset($query, $asset, $fiat)
    {
        return $query->where('asset', $asset)->where('fiat', $fiat);
    }
}