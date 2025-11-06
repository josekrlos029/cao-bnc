<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class BybitCredential extends Model
{
    use HasFactory;

    protected $table = 'bybit_credentials';

    protected $fillable = [
        'user_id',
        'api_key_encrypted',
        'secret_key_encrypted',
        'is_testnet',
        'is_active',
        'last_used_at',
        'last_error',
    ];

    protected $casts = [
        'is_testnet' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_encrypted',
        'secret_key_encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getApiKeyAttribute(): string
    {
        return Crypt::decryptString($this->api_key_encrypted);
    }

    public function getSecretKeyAttribute(): string
    {
        return Crypt::decryptString($this->secret_key_encrypted);
    }

    public function setApiKeyAttribute($value): void
    {
        $this->api_key_encrypted = Crypt::encryptString($value);
    }

    public function setSecretKeyAttribute($value): void
    {
        $this->secret_key_encrypted = Crypt::encryptString($value);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

