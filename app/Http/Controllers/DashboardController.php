<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\BotConfiguration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        $userId = auth()->id();
        $now = Carbon::now();
        
        // Períodos de comparación
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();
        $thisWeek = $now->copy()->startOfWeek();
        $lastWeek = $now->copy()->subWeek()->startOfWeek();
        $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $last7Days = $now->copy()->subDays(7)->startOfDay();
        $last30Days = $now->copy()->subDays(30)->startOfDay();

        $baseQuery = Transaction::where('user_id', $userId);

        // Estadísticas generales
        $totalTransactions = (clone $baseQuery)->count();
        $completedTransactions = (clone $baseQuery)->completed()->count();
        $pendingTransactions = (clone $baseQuery)->where('status', 'pending')->count();

        // Volumen total
        $totalVolume = (clone $baseQuery)->completed()->sum('total_price');
        $todayVolume = (clone $baseQuery)->completed()
            ->where('binance_create_time', '>=', $today)
            ->sum('total_price');
        $yesterdayVolume = (clone $baseQuery)->completed()
            ->whereBetween('binance_create_time', [$yesterday, $yesterdayEnd])
            ->sum('total_price');
        $thisWeekVolume = (clone $baseQuery)->completed()
            ->where('binance_create_time', '>=', $thisWeek)
            ->sum('total_price');
        $lastWeekVolume = (clone $baseQuery)->completed()
            ->whereBetween('binance_create_time', [$lastWeek, $lastWeekEnd])
            ->sum('total_price');

        // Comisiones totales
        $totalCommissions = (clone $baseQuery)->completed()
            ->selectRaw('SUM(COALESCE(commission, 0) + COALESCE(taker_fee, 0) + COALESCE(network_fee, 0)) as total')
            ->value('total') ?? 0;

        // Bots activos
        $activeBots = BotConfiguration::where('user_id', $userId)
            ->where('is_active', true)
            ->count();

        // Moneda principal
        $primaryCurrency = (clone $baseQuery)
            ->completed()
            ->whereNotNull('fiat_type')
            ->select('fiat_type', DB::raw('COUNT(*) as count'))
            ->groupBy('fiat_type')
            ->orderBy('count', 'desc')
            ->value('fiat_type') ?? 'USD';

        // Operaciones por día (últimos 7 días)
        $transactionsByDay = (clone $baseQuery)
            ->completed()
            ->where('binance_create_time', '>=', $last7Days)
            ->select(
                DB::raw('DATE(binance_create_time) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(COALESCE(total_price, 0)) as volume')
            )
            ->groupBy(DB::raw('DATE(binance_create_time)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // Operaciones por tipo (BUY/SELL)
        $operationsByType = (clone $baseQuery)
            ->completed()
            ->whereNotNull('order_type')
            ->select('order_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(COALESCE(total_price, 0)) as volume'))
            ->groupBy('order_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->order_type,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // Top 5 activos por volumen
        $topAssets = (clone $baseQuery)
            ->completed()
            ->select('asset_type', 
                DB::raw('COUNT(*) as count'), 
                DB::raw('SUM(COALESCE(total_price, 0)) as volume')
            )
            ->groupBy('asset_type')
            ->orderBy('volume', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'asset' => $item->asset_type,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // Operaciones por estado
        $operationsByStatus = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
            });

        // Transacciones recientes (últimas 5)
        $recentTransactions = (clone $baseQuery)
            ->orderBy('binance_create_time', 'desc')
            ->limit(5)
            ->get(['id', 'order_number', 'transaction_type', 'asset_type', 'order_type', 'total_price', 'status', 'binance_create_time']);

        // Calcular cambios porcentuales
        $volumeChangeToday = $yesterdayVolume > 0 
            ? (($todayVolume - $yesterdayVolume) / $yesterdayVolume) * 100 
            : 0;
        
        $volumeChangeWeek = $lastWeekVolume > 0 
            ? (($thisWeekVolume - $lastWeekVolume) / $lastWeekVolume) * 100 
            : 0;

        // Tasa de éxito
        $successRate = $totalTransactions > 0 
            ? ($completedTransactions / $totalTransactions) * 100 
            : 0;

        return Inertia::render('Dashboard', [
            'currency' => $primaryCurrency,
            'stats' => [
                'total_transactions' => $totalTransactions,
                'completed_transactions' => $completedTransactions,
                'pending_transactions' => $pendingTransactions,
                'active_bots' => $activeBots,
                'total_volume' => (float) $totalVolume,
                'today_volume' => (float) $todayVolume,
                'yesterday_volume' => (float) $yesterdayVolume,
                'this_week_volume' => (float) $thisWeekVolume,
                'last_week_volume' => (float) $lastWeekVolume,
                'total_commissions' => (float) $totalCommissions,
                'success_rate' => round($successRate, 2),
                'volume_change_today' => round($volumeChangeToday, 2),
                'volume_change_week' => round($volumeChangeWeek, 2),
            ],
            'charts' => [
                'transactions_by_day' => $transactionsByDay,
                'operations_by_type' => $operationsByType,
                'top_assets' => $topAssets,
                'operations_by_status' => $operationsByStatus,
            ],
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
