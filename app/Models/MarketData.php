<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketData extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset',
        'fiat',
        'price',
        'source',
        'metadata',
        'data_timestamp',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'metadata' => 'array',
        'data_timestamp' => 'datetime',
    ];

    public function scopeByAsset($query, $asset, $fiat)
    {
        return $query->where('asset', $asset)->where('fiat', $fiat);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('data_timestamp', 'desc');
    }
}