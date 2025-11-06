<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TradeHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * Mostrar el tablero de reportes
     */
    public function index(Request $request): Response
    {
        $userId = auth()->id();
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $baseQuery = Transaction::where('user_id', $userId)
            ->whereBetween('binance_create_time', [$start, $end]);

        // Filtro por exchange
        if ($request->filled('exchange')) {
            $baseQuery->where('exchange', $request->exchange);
        }

        // 1. Volumen total
        $totalVolume = (clone $baseQuery)
            ->completed()
            ->sum('total_price');

        // 2. Precio promedio de operación
        $avgPrice = (clone $baseQuery)
            ->completed()
            ->whereNotNull('price')
            ->avg('price');

        // 3. Operaciones realizadas
        $totalOperations = (clone $baseQuery)->count();
        $completedOperations = (clone $baseQuery)->completed()->count();

        // 4. Comisiones por compra
        $buyCommissions = (clone $baseQuery)
            ->where('order_type', 'BUY')
            ->completed()
            ->sum('commission');

        // 5. Comisiones por venta
        $sellCommissions = (clone $baseQuery)
            ->where('order_type', 'SELL')
            ->completed()
            ->sum('commission');

        // 6. Comisiones totales (incluyendo taker_fee y network_fee)
        $totalCommissions = (clone $baseQuery)
            ->completed()
            ->selectRaw('SUM(COALESCE(commission, 0) + COALESCE(taker_fee, 0) + COALESCE(network_fee, 0)) as total')
            ->value('total') ?? 0;

        // 7. Operaciones por método de pago
        $operationsByPaymentMethod = (clone $baseQuery)
            ->completed()
            ->whereNotNull('payment_method')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price) as volume'))
            ->groupBy('payment_method')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->payment_method ?? 'N/A',
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // 8. Operaciones por tipo (BUY/SELL)
        $operationsByType = (clone $baseQuery)
            ->completed()
            ->whereNotNull('order_type')
            ->select('order_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price) as volume'))
            ->groupBy('order_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->order_type,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // 9. Operaciones por tipo de transacción
        $operationsByTransactionType = (clone $baseQuery)
            ->select('transaction_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(COALESCE(total_price, 0)) as volume'))
            ->groupBy('transaction_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->transaction_type,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // 10. Operaciones por estado
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

        // 11. Operaciones por activo (asset_type)
        $operationsByAsset = (clone $baseQuery)
            ->completed()
            ->select('asset_type', 
                DB::raw('COUNT(*) as count'), 
                DB::raw('SUM(COALESCE(total_price, 0)) as volume'),
                DB::raw('AVG(price) as avg_price'),
                DB::raw('SUM(COALESCE(commission, 0) + COALESCE(taker_fee, 0)) as total_fees')
            )
            ->groupBy('asset_type')
            ->orderBy('volume', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'asset' => $item->asset_type,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                    'avg_price' => (float) $item->avg_price,
                    'total_fees' => (float) $item->total_fees,
                ];
            });

        // 12. Volumen por día (tendencia temporal)
        $volumeByDay = (clone $baseQuery)
            ->completed()
            ->select(
                DB::raw('DATE(binance_create_time) as date'),
                DB::raw('SUM(COALESCE(total_price, 0)) as volume'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(binance_create_time)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'volume' => (float) $item->volume,
                    'count' => $item->count,
                ];
            });

        // 13. Comparación de compras vs ventas por día
        $buyVsSellByDay = (clone $baseQuery)
            ->completed()
            ->whereNotNull('order_type')
            ->select(
                DB::raw('DATE(binance_create_time) as date'),
                'order_type',
                DB::raw('SUM(COALESCE(total_price, 0)) as volume'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(binance_create_time)'), 'order_type')
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('date')
            ->map(function ($dayGroup) {
                $buy = $dayGroup->firstWhere('order_type', 'BUY');
                $sell = $dayGroup->firstWhere('order_type', 'SELL');
                return [
                    'date' => $dayGroup->first()->date,
                    'buy_volume' => $buy ? (float) $buy->volume : 0,
                    'sell_volume' => $sell ? (float) $sell->volume : 0,
                    'buy_count' => $buy ? $buy->count : 0,
                    'sell_count' => $sell ? $sell->count : 0,
                ];
            })
            ->values();

        // 14. Tasa de éxito de transacciones
        $successRate = $totalOperations > 0 
            ? ($completedOperations / $totalOperations) * 100 
            : 0;

        // 15. Operaciones por mes (para vista mensual)
        $operationsByMonth = (clone $baseQuery)
            ->completed()
            ->select(
                DB::raw('DATE_FORMAT(binance_create_time, "%Y-%m") as month'),
                DB::raw('SUM(COALESCE(total_price, 0)) as volume'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(COALESCE(commission, 0) + COALESCE(taker_fee, 0)) as fees')
            )
            ->groupBy(DB::raw('DATE_FORMAT(binance_create_time, "%Y-%m")'))
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'volume' => (float) $item->volume,
                    'count' => $item->count,
                    'fees' => (float) $item->fees,
                ];
            });

        // 16. Top contrapartes (más operaciones)
        $topCounterParties = (clone $baseQuery)
            ->completed()
            ->whereNotNull('counter_party')
            ->select('counter_party', DB::raw('COUNT(*) as count'), DB::raw('SUM(COALESCE(total_price, 0)) as volume'))
            ->groupBy('counter_party')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'counter_party' => $item->counter_party,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // 17. Distribución de operaciones por hora del día
        $operationsByHour = (clone $baseQuery)
            ->completed()
            ->select(
                DB::raw('HOUR(binance_create_time) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(COALESCE(total_price, 0)) as volume')
            )
            ->groupBy(DB::raw('HOUR(binance_create_time)'))
            ->orderBy('hour', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'count' => $item->count,
                    'volume' => (float) $item->volume,
                ];
            });

        // 18. Margen promedio (precio de venta - precio de compra)
        // Esto requiere comparar operaciones de compra y venta del mismo activo
        $avgBuyPrice = (clone $baseQuery)
            ->where('order_type', 'BUY')
            ->completed()
            ->whereNotNull('asset_type')
            ->whereNotNull('price')
            ->select('asset_type', DB::raw('AVG(price) as avg_price'))
            ->groupBy('asset_type')
            ->get()
            ->keyBy('asset_type');

        $avgSellPrice = (clone $baseQuery)
            ->where('order_type', 'SELL')
            ->completed()
            ->whereNotNull('asset_type')
            ->whereNotNull('price')
            ->select('asset_type', DB::raw('AVG(price) as avg_price'))
            ->groupBy('asset_type')
            ->get()
            ->keyBy('asset_type');

        $profitabilityByAsset = collect($avgBuyPrice->keys()->merge($avgSellPrice->keys())->unique())
            ->map(function ($asset) use ($avgBuyPrice, $avgSellPrice) {
                $buyPrice = $avgBuyPrice->get($asset)?->avg_price ?? 0;
                $sellPrice = $avgSellPrice->get($asset)?->avg_price ?? 0;
                $margin = $sellPrice > 0 && $buyPrice > 0 ? (($sellPrice - $buyPrice) / $buyPrice) * 100 : 0;
                
                return [
                    'asset' => $asset,
                    'avg_buy_price' => (float) $buyPrice,
                    'avg_sell_price' => (float) $sellPrice,
                    'margin_percentage' => round($margin, 2),
                ];
            })
            ->filter(function ($item) {
                return $item['avg_buy_price'] > 0 && $item['avg_sell_price'] > 0;
            })
            ->sortByDesc('margin_percentage')
            ->values();

        // Opciones para filtros (solo del usuario)
        $filterOptions = [
            'exchanges' => Transaction::where('user_id', $userId)->distinct()->pluck('exchange')->filter()->values(),
        ];

        return Inertia::render('Reports/Index', [
            'dateRange' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'filters' => $request->only(['exchange']),
            'filterOptions' => $filterOptions,
            'metrics' => [
                'total_volume' => (float) $totalVolume,
                'avg_price' => (float) $avgPrice,
                'total_operations' => $totalOperations,
                'completed_operations' => $completedOperations,
                'success_rate' => round($successRate, 2),
                'buy_commissions' => (float) $buyCommissions,
                'sell_commissions' => (float) $sellCommissions,
                'total_commissions' => (float) $totalCommissions,
            ],
            'charts' => [
                'operations_by_payment_method' => $operationsByPaymentMethod,
                'operations_by_type' => $operationsByType,
                'operations_by_transaction_type' => $operationsByTransactionType,
                'operations_by_status' => $operationsByStatus,
                'operations_by_asset' => $operationsByAsset,
                'volume_by_day' => $volumeByDay,
                'buy_vs_sell_by_day' => $buyVsSellByDay,
                'operations_by_month' => $operationsByMonth,
                'top_counter_parties' => $topCounterParties,
                'operations_by_hour' => $operationsByHour,
                'profitability_by_asset' => $profitabilityByAsset,
            ],
        ]);
    }
}

