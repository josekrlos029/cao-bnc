<?php

namespace App\Jobs;

use App\Models\BotConfiguration;
use App\Models\BotActionLog;
use App\Models\P2PAd;
use App\Models\MarketData;
use App\Services\BinanceP2PService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBotStrategy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Obtener configuraciones activas
            $activeConfigs = BotConfiguration::where('is_active', true)->get();

            foreach ($activeConfigs as $config) {
                $this->processConfiguration($config);
            }

            Log::info('ProcessBotStrategy completed', [
                'processed_configs' => $activeConfigs->count()
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessBotStrategy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function processConfiguration(BotConfiguration $config): void
    {
        try {
            // Obtener datos de mercado actuales
            $marketData = MarketData::where('asset', $config->asset)
                ->where('fiat', $config->fiat)
                ->latest('data_timestamp')
                ->first();

            if (!$marketData) {
                Log::warning('No market data found for configuration', [
                    'config_id' => $config->id,
                    'asset' => $config->asset,
                    'fiat' => $config->fiat
                ]);
                return;
            }

            // Obtener anuncios competidores
            $competitors = P2PAd::where('asset', $config->asset)
                ->where('fiat', $config->fiat)
                ->where('status', 'active')
                ->orderBy('price', $config->operation === 'BUY' ? 'asc' : 'desc')
                ->limit(20)
                ->get();

            // Analizar posición actual
            $currentPosition = $this->analyzeCurrentPosition($config, $competitors, $marketData);

            // Generar sugerencias basadas en la estrategia
            $suggestions = $this->generateSuggestions($config, $currentPosition, $competitors, $marketData);

            // Crear logs de acciones sugeridas
            foreach ($suggestions as $suggestion) {
                BotActionLog::create([
                    'bot_configuration_id' => $config->id,
                    'action_type' => 'suggest',
                    'action_data' => $suggestion,
                    'status' => 'pending_approval'
                ]);
            }

            // Actualizar timestamp de última verificación
            $config->last_checked_at = now();
            $config->save();

        } catch (\Exception $e) {
            Log::error('Error processing configuration', [
                'config_id' => $config->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function analyzeCurrentPosition(BotConfiguration $config, $competitors, MarketData $marketData): array
    {
        // Buscar anuncio del usuario en la lista de competidores
        $userAd = $competitors->where('ad_number', $config->ad_number)->first();
        
        $position = null;
        $priceDifference = null;
        
        if ($userAd) {
            $position = $userAd->position ?? $competitors->search($userAd) + 1;
            $priceDifference = $userAd->price - $marketData->price;
        }

        return [
            'position' => $position,
            'price_difference' => $priceDifference,
            'market_price' => $marketData->price,
            'competitor_count' => $competitors->count()
        ];
    }

    private function generateSuggestions(BotConfiguration $config, array $currentPosition, $competitors, MarketData $marketData): array
    {
        $suggestions = [];

        // Sugerencia de ajuste de precio basada en posición
        if ($currentPosition['position'] !== null) {
            if ($currentPosition['position'] > $config->max_positions) {
                $suggestions[] = $this->createPriceAdjustmentSuggestion($config, $competitors, 'increase_competitiveness');
            } elseif ($currentPosition['position'] < $config->min_positions) {
                $suggestions[] = $this->createPriceAdjustmentSuggestion($config, $competitors, 'decrease_competitiveness');
            }
        }

        // Sugerencia basada en diferencia USD
        if ($config->min_usd_diff && $config->max_usd_diff) {
            if ($currentPosition['price_difference'] < $config->min_usd_diff) {
                $suggestions[] = $this->createPriceAdjustmentSuggestion($config, $competitors, 'increase_price');
            } elseif ($currentPosition['price_difference'] > $config->max_usd_diff) {
                $suggestions[] = $this->createPriceAdjustmentSuggestion($config, $competitors, 'decrease_price');
            }
        }

        // Sugerencia basada en perfil
        $suggestions = array_merge($suggestions, $this->generateProfileBasedSuggestions($config, $currentPosition, $competitors));

        return $suggestions;
    }

    private function createPriceAdjustmentSuggestion(BotConfiguration $config, $competitors, string $action): array
    {
        $topCompetitor = $competitors->first();
        $newPrice = $config->min_price ?? $topCompetitor->price ?? 0;

        switch ($action) {
            case 'increase_competitiveness':
                $newPrice = $topCompetitor->price - ($config->increment ?? 1000);
                break;
            case 'decrease_competitiveness':
                $newPrice = $topCompetitor->price + ($config->difference ?? 1000);
                break;
            case 'increase_price':
                $newPrice = ($config->min_price ?? $topCompetitor->price) + ($config->increment ?? 1000);
                break;
            case 'decrease_price':
                $newPrice = ($config->max_price ?? $topCompetitor->price) - ($config->difference ?? 1000);
                break;
        }

        return [
            'action' => 'update_price',
            'reason' => $action,
            'current_price' => $config->min_price ?? $topCompetitor->price ?? 0,
            'suggested_price' => max($newPrice, 0),
            'priority' => $this->getPriorityByProfile($config->profile)
        ];
    }

    private function generateProfileBasedSuggestions(BotConfiguration $config, array $currentPosition, $competitors): array
    {
        $suggestions = [];

        switch ($config->profile) {
            case 'agresivo':
                if ($currentPosition['position'] > 3) {
                    $suggestions[] = [
                        'action' => 'aggressive_price_cut',
                        'reason' => 'aggressive_profile_position_improvement',
                        'suggested_price' => $competitors->first()->price - 2000,
                        'priority' => 'high'
                    ];
                }
                break;

            case 'moderado':
                if ($currentPosition['position'] > 5) {
                    $suggestions[] = [
                        'action' => 'moderate_price_adjustment',
                        'reason' => 'moderate_profile_position_improvement',
                        'suggested_price' => $competitors->first()->price - 1000,
                        'priority' => 'medium'
                    ];
                }
                break;

            case 'conservador':
                if ($currentPosition['position'] > 8) {
                    $suggestions[] = [
                        'action' => 'conservative_price_adjustment',
                        'reason' => 'conservative_profile_position_improvement',
                        'suggested_price' => $competitors->first()->price - 500,
                        'priority' => 'low'
                    ];
                }
                break;
        }

        return $suggestions;
    }

    private function getPriorityByProfile(string $profile): string
    {
        return match($profile) {
            'agresivo' => 'high',
            'moderado' => 'medium',
            'conservador' => 'low',
            default => 'medium'
        };
    }
}