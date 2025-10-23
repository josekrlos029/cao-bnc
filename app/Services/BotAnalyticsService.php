<?php

namespace App\Services;

use App\Models\BotConfiguration;
use App\Models\BotActionLog;
use App\Models\TradeHistory;
use App\Models\P2PAd;
use App\Models\MarketData;
use Illuminate\Support\Facades\DB;

class BotAnalyticsService
{
    /**
     * Calcular ROI de trades ejecutados
     */
    public function calculateROI(int $userId, string $period = '30d'): array
    {
        $dateFrom = $this->getDateFromPeriod($period);
        
        $trades = TradeHistory::whereHas('botConfiguration', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->where('status', 'completed')
        ->where('completed_at', '>=', $dateFrom)
        ->get();

        $totalInvested = $trades->sum('total');
        $totalProfit = $trades->sum(function($trade) {
            // Calcular profit basado en diferencia de precio
            return $trade->amount * ($trade->price - $this->getMarketPriceAtTime($trade->asset, $trade->fiat, $trade->completed_at));
        });

        $roi = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

        return [
            'total_trades' => $trades->count(),
            'total_invested' => $totalInvested,
            'total_profit' => $totalProfit,
            'roi_percentage' => round($roi, 2),
            'period' => $period
        ];
    }

    /**
     * Métricas de performance por perfil
     */
    public function getPerformanceByProfile(int $userId): array
    {
        $profiles = ['agresivo', 'moderado', 'conservador'];
        $performance = [];

        foreach ($profiles as $profile) {
            $config = BotConfiguration::where('user_id', $userId)
                ->where('profile', $profile)
                ->first();

            if (!$config) continue;

            $actions = BotActionLog::where('bot_configuration_id', $config->id)
                ->where('status', 'executed')
                ->get();

            $successRate = $actions->count() > 0 ? 
                ($actions->where('result', 'like', '%exitosamente%')->count() / $actions->count()) * 100 : 0;

            $performance[$profile] = [
                'total_actions' => $actions->count(),
                'success_rate' => round($successRate, 2),
                'avg_response_time' => $this->calculateAvgResponseTime($actions),
                'is_active' => $config->is_active
            ];
        }

        return $performance;
    }

    /**
     * Análisis de competencia
     */
    public function analyzeCompetition(string $asset, string $fiat): array
    {
        $competitors = P2PAd::where('asset', $asset)
            ->where('fiat', $fiat)
            ->where('status', 'active')
            ->orderBy('price', 'asc')
            ->get();

        if ($competitors->isEmpty()) {
            return [];
        }

        $prices = $competitors->pluck('price');
        $avgPrice = $prices->avg();
        $minPrice = $prices->min();
        $maxPrice = $prices->max();

        return [
            'total_competitors' => $competitors->count(),
            'avg_price' => round($avgPrice, 2),
            'min_price' => round($minPrice, 2),
            'max_price' => round($maxPrice, 2),
            'price_spread' => round($maxPrice - $minPrice, 2),
            'top_5_positions' => $competitors->take(5)->map(function($ad) {
                return [
                    'position' => $ad->position,
                    'price' => $ad->price,
                    'advertiser' => $ad->advertiser_nickname
                ];
            })
        ];
    }

    /**
     * Tiempo promedio en posiciones top
     */
    public function getAverageTimeInTopPositions(int $userId, int $topPosition = 5): array
    {
        $config = BotConfiguration::where('user_id', $userId)->first();
        
        if (!$config) {
            return [];
        }

        // Simular análisis de tiempo en posiciones top
        // En una implementación real, esto requeriría un historial de posiciones
        $actions = BotActionLog::where('bot_configuration_id', $config->id)
            ->where('action_type', 'execute')
            ->where('status', 'executed')
            ->get();

        $timeInTopPositions = $actions->count() * 30; // Simulación: 30 minutos por acción

        return [
            'avg_time_in_top_' . $topPosition => $timeInTopPositions,
            'total_monitoring_time' => $actions->sum(function($action) {
                return $action->executed_at ? $action->executed_at->diffInMinutes($action->created_at) : 0;
            })
        ];
    }

    /**
     * Sugerencias de optimización
     */
    public function getOptimizationSuggestions(int $userId): array
    {
        $suggestions = [];
        $config = BotConfiguration::where('user_id', $userId)->first();

        if (!$config) {
            return $suggestions;
        }

        // Analizar acciones recientes
        $recentActions = BotActionLog::where('bot_configuration_id', $config->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $successRate = $recentActions->count() > 0 ? 
            ($recentActions->where('status', 'executed')->count() / $recentActions->count()) * 100 : 0;

        if ($successRate < 70) {
            $suggestions[] = [
                'type' => 'performance',
                'message' => 'La tasa de éxito es baja (' . round($successRate, 1) . '%). Considera ajustar los parámetros de precio.',
                'priority' => 'high'
            ];
        }

        // Analizar posición promedio
        $competition = $this->analyzeCompetition($config->asset, $config->fiat);
        if ($competition && $config->min_positions > $competition['total_competitors'] / 2) {
            $suggestions[] = [
                'type' => 'positioning',
                'message' => 'Tu objetivo de posición mínima es muy alto para el mercado actual.',
                'priority' => 'medium'
            ];
        }

        // Sugerencias basadas en perfil
        if ($config->profile === 'conservador' && $config->min_usd_diff < 500) {
            $suggestions[] = [
                'type' => 'profile',
                'message' => 'Para un perfil conservador, considera aumentar la diferencia USD mínima.',
                'priority' => 'low'
            ];
        }

        return $suggestions;
    }

    /**
     * Dashboard Analytics - Métricas generales
     */
    public function getDashboardMetrics(int $userId): array
    {
        $config = BotConfiguration::where('user_id', $userId)->first();
        
        if (!$config) {
            return [
                'total_transactions' => 0,
                'active_bots' => 0,
                'total_volume' => 0,
                'profit_loss' => 0
            ];
        }

        $trades = TradeHistory::whereHas('botConfiguration', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->where('status', 'completed');

        $totalVolume = $trades->sum('total');
        $totalTrades = $trades->count();

        // Calcular P&L aproximado
        $profitLoss = $trades->sum(function($trade) {
            return $trade->amount * ($trade->price - $this->getMarketPriceAtTime($trade->asset, $trade->fiat, $trade->completed_at));
        });

        return [
            'total_transactions' => $totalTrades,
            'active_bots' => BotConfiguration::where('user_id', $userId)->where('is_active', true)->count(),
            'total_volume' => $totalVolume,
            'profit_loss' => $profitLoss
        ];
    }

    /**
     * Gráficos de trades ejecutados
     */
    public function getTradesChart(int $userId, string $period = '30d'): array
    {
        $dateFrom = $this->getDateFromPeriod($period);
        
        $trades = TradeHistory::whereHas('botConfiguration', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->where('status', 'completed')
        ->where('completed_at', '>=', $dateFrom)
        ->orderBy('completed_at')
        ->get();

        return $trades->groupBy(function($trade) {
            return $trade->completed_at->format('Y-m-d');
        })->map(function($dayTrades) {
            return [
                'count' => $dayTrades->count(),
                'volume' => $dayTrades->sum('total'),
                'profit' => $dayTrades->sum(function($trade) {
                    return $trade->amount * ($trade->price - $this->getMarketPriceAtTime($trade->asset, $trade->fiat, $trade->completed_at));
                })
            ];
        })->toArray();
    }

    /**
     * Comparativa de estrategias
     */
    public function compareStrategies(int $userId): array
    {
        $strategies = [];
        $profiles = ['agresivo', 'moderado', 'conservador'];

        foreach ($profiles as $profile) {
            $config = BotConfiguration::where('user_id', $userId)
                ->where('profile', $profile)
                ->first();

            if (!$config) continue;

            $roi = $this->calculateROI($userId, '30d');
            $performance = $this->getPerformanceByProfile($userId);

            $strategies[$profile] = [
                'roi' => $roi['roi_percentage'],
                'success_rate' => $performance[$profile]['success_rate'] ?? 0,
                'total_actions' => $performance[$profile]['total_actions'] ?? 0,
                'is_active' => $config->is_active
            ];
        }

        return $strategies;
    }

    private function getDateFromPeriod(string $period): \Carbon\Carbon
    {
        return match($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30)
        };
    }

    private function calculateAvgResponseTime($actions): float
    {
        if ($actions->isEmpty()) return 0;

        $totalTime = $actions->sum(function($action) {
            return $action->executed_at ? $action->executed_at->diffInMinutes($action->created_at) : 0;
        });

        return round($totalTime / $actions->count(), 2);
    }

    private function getMarketPriceAtTime(string $asset, string $fiat, \Carbon\Carbon $time): float
    {
        $marketData = MarketData::where('asset', $asset)
            ->where('fiat', $fiat)
            ->where('data_timestamp', '<=', $time)
            ->latest('data_timestamp')
            ->first();

        return $marketData ? $marketData->price : 0;
    }
}
