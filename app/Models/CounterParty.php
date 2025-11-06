<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class CounterParty extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exchange',
        'counter_party', // nickname
        'full_name', // buyerName o sellerName
        'merchant_no',
        'counter_party_dni',
        'dni_type',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByExchange($query, string $exchange)
    {
        return $query->where('exchange', $exchange);
    }

    public function scopeByMerchantNo($query, string $merchantNo)
    {
        return $query->where('merchant_no', $merchantNo);
    }

    /**
     * Buscar o crear un CounterParty para una transacción
     * 
     * @param int $user_id
     * @param string $exchange
     * @param string|null $counter_party (nickname)
     * @param string|null $merchant_no
     * @param string|null $full_name
     * @return CounterParty
     */
    public static function findOrCreateForTransaction(
        int $user_id,
        string $exchange,
        ?string $counter_party = null,
        ?string $merchant_no = null,
        ?string $full_name = null
    ): self {
        // Primero intentar buscar por merchant_no si está disponible
        if ($merchant_no) {
            $found = self::where('user_id', $user_id)
                ->where('exchange', $exchange)
                ->where('merchant_no', $merchant_no)
                ->first();
            
            if ($found) {
                // Actualizar counter_party con el nickname si se proporciona (para asegurar que sea correcto)
                if ($counter_party && $found->counter_party !== $counter_party) {
                    $found->counter_party = $counter_party;
                }
                // Actualizar full_name si se proporciona y no existe o es diferente
                if ($full_name && $found->full_name !== $full_name) {
                    $found->full_name = $full_name;
                }
                if ($found->isDirty()) {
                    $found->save();
                }
                return $found;
            }
        }

        // Si no se encontró por merchant_no, buscar por counter_party
        if ($counter_party) {
            $found = self::where('user_id', $user_id)
                ->where('exchange', $exchange)
                ->where('counter_party', $counter_party)
                ->first();
            
            if ($found) {
                // Si se encontró pero no tenía merchant_no y ahora sí lo tenemos, actualizarlo
                if ($merchant_no && !$found->merchant_no) {
                    $found->merchant_no = $merchant_no;
                }
                // Actualizar full_name si se proporciona y no existe o es diferente
                if ($full_name && $found->full_name !== $full_name) {
                    $found->full_name = $full_name;
                }
                if ($found->isDirty()) {
                    $found->save();
                }
                return $found;
            }
        }

        // Si no existe, crear uno nuevo
        $newCounterParty = self::create([
            'user_id' => $user_id,
            'exchange' => $exchange,
            'counter_party' => $counter_party,
            'full_name' => $full_name,
            'merchant_no' => $merchant_no,
        ]);
        
        return $newCounterParty;
    }
    
    /**
     * Actualizar todas las transacciones relacionadas con este CounterParty
     * cuando se actualiza o crea con dni_type y counter_party_dni
     */
    public function updateRelatedTransactions(): void
    {
        // Solo actualizar si tenemos dni_type y counter_party_dni
        if (!$this->counter_party_dni || !$this->dni_type || !$this->counter_party) {
            return;
        }
        
        // Buscar todas las transacciones de este usuario y exchange que tengan este counter_party
        // pero que no tengan dni_type o counter_party_dni
        $updated = Transaction::where('user_id', $this->user_id)
            ->where('exchange', $this->exchange)
            ->where('counter_party', $this->counter_party)
            ->where(function ($query) {
                $query->whereNull('dni_type')
                    ->orWhereNull('counter_party_dni')
                    ->orWhere('dni_type', '!=', $this->dni_type)
                    ->orWhere('counter_party_dni', '!=', $this->counter_party_dni);
            })
            ->update([
                'dni_type' => $this->dni_type,
                'counter_party_dni' => $this->counter_party_dni,
            ]);
        
        if ($updated > 0) {
            Log::info('Updated related transactions with CounterParty info', [
                'counter_party_id' => $this->id,
                'counter_party' => $this->counter_party,
                'transactions_updated' => $updated,
                'dni_type' => $this->dni_type,
            ]);
        }
    }
    
    /**
     * Hook para actualizar transacciones después de guardar
     */
    protected static function booted(): void
    {
        static::saved(function (CounterParty $counterParty) {
            // Si se actualizó o creó con dni_type y counter_party_dni, actualizar transacciones
            if ($counterParty->counter_party_dni && $counterParty->dni_type) {
                $counterParty->updateRelatedTransactions();
            }
        });
    }
}
