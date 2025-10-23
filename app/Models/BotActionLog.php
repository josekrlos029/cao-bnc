<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotActionLog extends Model
{
    use HasFactory;

    protected $table = 'bot_actions_log';

    protected $fillable = [
        'bot_configuration_id',
        'action_type',
        'action_data',
        'status',
        'result',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'executed_at' => 'datetime',
    ];

    public function botConfiguration(): BelongsTo
    {
        return $this->belongsTo(BotConfiguration::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeExecuted($query)
    {
        return $query->where('status', 'executed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('action_type', $type);
    }
}