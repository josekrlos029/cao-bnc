<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'advertisement_order_number',
        'trade_id',
        'transaction_id',
        'transaction_type',
        'exchange',
        'order_type',
        'asset_type',
        'fiat_type',
        'total_price',
        'price',
        'quantity',
        'amount',
        'taker_fee',
        'taker_fee_rate',
        'commission',
        'network_fee',
        'payment_method',
        'account_number',
        'counter_party',
        'counter_party_full_name',
        'counter_party_dni',
        'dni_type',
        'my_payment_method_id',
        'status',
        'binance_create_time',
        'binance_update_time',
        'metadata',
        'notes',
        'source_endpoint',
        'last_synced_at',
        'enrichment_status',
        'is_manual_entry',
        'user_id',
    ];

    protected $casts = [
        'total_price' => 'decimal:8',
        'price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'amount' => 'decimal:8',
        'taker_fee' => 'decimal:8',
        'taker_fee_rate' => 'decimal:8',
        'commission' => 'decimal:8',
        'network_fee' => 'decimal:8',
        'metadata' => 'array',
        'binance_create_time' => 'datetime',
        'binance_update_time' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_manual_entry' => 'boolean',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtener el CounterParty relacionado si existe
     * Busca por counter_party name, exchange y user_id
     */
    public function getCounterParty()
    {
        if (!$this->counter_party || !$this->user_id || !$this->exchange) {
            return null;
        }

        return CounterParty::where('user_id', $this->user_id)
            ->where('exchange', $this->exchange)
            ->where('counter_party', $this->counter_party)
            ->first();
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByAsset($query, string $asset, string $fiat = null)
    {
        $query = $query->where('asset_type', $asset);
        if ($fiat) {
            $query->where('fiat_type', $fiat);
        }
        return $query;
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeManualEntries($query)
    {
        return $query->where('is_manual_entry', true);
    }

    public function scopeSynced($query)
    {
        return $query->whereNotNull('last_synced_at');
    }

    public function scopeInDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('binance_create_time', [$start, $end]);
    }

    // Métodos de utilidad
    public function isP2P(): bool
    {
        return $this->transaction_type === 'p2p_order';
    }

    public function isSpotTrade(): bool
    {
        return $this->transaction_type === 'spot_trade';
    }

    public function isDeposit(): bool
    {
        return $this->transaction_type === 'deposit';
    }

    public function isWithdrawal(): bool
    {
        return $this->transaction_type === 'withdrawal';
    }

    public function getTotalValueAttribute(): float
    {
        return $this->total_price ?? ($this->price * $this->quantity) ?? 0;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount ?? 0, 2);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price ?? 0, 8);
    }

    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity ?? 0, 8);
    }

    // Método para marcar como sincronizado
    public function markAsSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    // Método para obtener el identificador único de Binance
    public function getBinanceIdentifier(): string
    {
        return $this->order_number ?? $this->trade_id ?? $this->transaction_id ?? '';
    }

    /**
     * Accessor para counter_party_dni
     * Usa el valor de CounterParty si existe, sino el campo directo
     */
    public function getCounterPartyDniAttribute($value)
    {
        // Si ya hay un valor directo, usar el valor directo
        if ($value) {
            return $value;
        }

        // Intentar obtener de CounterParty
        $counterParty = $this->getCounterParty();
        if ($counterParty && $counterParty->counter_party_dni) {
            return $counterParty->counter_party_dni;
        }

        return $value;
    }

    /**
     * Accessor para dni_type
     * Usa el valor de CounterParty si existe, sino el campo directo
     */
    public function getDniTypeAttribute($value)
    {
        // Si ya hay un valor directo, usar el valor directo
        if ($value) {
            return $value;
        }

        // Intentar obtener de CounterParty
        $counterParty = $this->getCounterParty();
        if ($counterParty && $counterParty->dni_type) {
            return $counterParty->dni_type;
        }

        return $value;
    }
}
