<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Jobs\SyncBinanceTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Mostrar el dashboard de transacciones
     */
    public function index(Request $request): View
    {
        $query = Transaction::query();

        // Filtros
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        if ($request->filled('fiat_type')) {
            $query->where('fiat_type', $request->fiat_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('binance_create_time', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('binance_create_time', '<=', $request->date_to);
        }

        if ($request->filled('is_manual')) {
            $query->where('is_manual_entry', $request->boolean('is_manual'));
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'binance_create_time');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $transactions = $query->paginate(50);

        // Estadísticas
        $stats = [
            'total_transactions' => Transaction::count(),
            'completed_transactions' => Transaction::completed()->count(),
            'pending_transactions' => Transaction::where('status', 'pending')->count(),
            'manual_entries' => Transaction::manualEntries()->count(),
            'total_value_usdt' => Transaction::completed()->sum('total_price'),
        ];

        // Opciones para filtros
        $filterOptions = [
            'transaction_types' => Transaction::distinct()->pluck('transaction_type')->filter(),
            'statuses' => Transaction::distinct()->pluck('status')->filter(),
            'asset_types' => Transaction::distinct()->pluck('asset_type')->filter(),
            'fiat_types' => Transaction::distinct()->pluck('fiat_type')->filter(),
        ];

        return view('transactions.index', compact('transactions', 'stats', 'filterOptions'));
    }

    /**
     * Mostrar detalles de una transacción
     */
    public function show(Transaction $transaction): View
    {
        $this->authorize('view', $transaction);
        return view('transactions.show', compact('transaction'));
    }

    /**
     * Crear nueva transacción manual
     */
    public function create(): View
    {
        return view('transactions.create');
    }

    /**
     * Guardar nueva transacción manual
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:100|unique:transactions,order_number',
            'transaction_type' => 'required|in:spot_trade,p2p_order,deposit,withdrawal,pay_transaction,c2c_order,manual_entry',
            'asset_type' => 'required|string|max:20',
            'fiat_type' => 'nullable|string|max:20',
            'order_type' => 'nullable|in:BUY,SELL',
            'quantity' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,processing,completed,cancelled,failed,expired',
            'binance_create_time' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|max:50',
            'counter_party' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = Transaction::create([
                'order_number' => $request->order_number,
                'transaction_type' => $request->transaction_type,
                'asset_type' => $request->asset_type,
                'fiat_type' => $request->fiat_type,
                'order_type' => $request->order_type,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'total_price' => $request->total_price,
                'status' => $request->status,
                'binance_create_time' => Carbon::parse($request->binance_create_time),
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
                'counter_party' => $request->counter_party,
                'is_manual_entry' => true,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar transacción
     */
    public function edit(Transaction $transaction): View
    {
        return view('transactions.edit', compact('transaction'));
    }

    /**
     * Actualizar transacción
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:100|unique:transactions,order_number,' . $transaction->id,
            'transaction_type' => 'required|in:spot_trade,p2p_order,deposit,withdrawal,pay_transaction,c2c_order,manual_entry',
            'asset_type' => 'required|string|max:20',
            'fiat_type' => 'nullable|string|max:20',
            'order_type' => 'nullable|in:BUY,SELL',
            'quantity' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,processing,completed,cancelled,failed,expired',
            'binance_create_time' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|max:50',
            'counter_party' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction->update([
                'order_number' => $request->order_number,
                'transaction_type' => $request->transaction_type,
                'asset_type' => $request->asset_type,
                'fiat_type' => $request->fiat_type,
                'order_type' => $request->order_type,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'total_price' => $request->total_price,
                'status' => $request->status,
                'binance_create_time' => Carbon::parse($request->binance_create_time),
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
                'counter_party' => $request->counter_party,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar transacción
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        try {
            // Solo permitir eliminar entradas manuales
            if (!$transaction->is_manual_entry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only manual entries can be deleted'
                ], 403);
            }

            $transaction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar sincronización manual
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:365',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startTime = null;
            $endTime = null;
            $userId = $request->user_id;

            if ($request->start_date && $request->end_date) {
                $startTime = Carbon::parse($request->start_date)->startOfDay();
                $endTime = Carbon::parse($request->end_date)->endOfDay();
            } elseif ($request->days) {
                $startTime = now()->subDays($request->days)->startOfDay();
                $endTime = now()->endOfDay();
            } else {
                $startTime = now()->subDays(7)->startOfDay();
                $endTime = now()->endOfDay();
            }

            SyncBinanceTransactions::dispatch($startTime, $endTime, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Sync job dispatched successfully',
                'sync_period' => [
                    'start' => $startTime->format('Y-m-d H:i:s'),
                    'end' => $endTime->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de transacciones
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_transactions' => Transaction::count(),
                'completed_transactions' => Transaction::completed()->count(),
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'manual_entries' => Transaction::manualEntries()->count(),
                'by_type' => Transaction::selectRaw('transaction_type, COUNT(*) as count')
                    ->groupBy('transaction_type')
                    ->pluck('count', 'transaction_type'),
                'by_status' => Transaction::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'by_asset' => Transaction::selectRaw('asset_type, COUNT(*) as count')
                    ->groupBy('asset_type')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->pluck('count', 'asset_type'),
                'total_value' => Transaction::completed()->sum('total_price'),
                'last_sync' => Transaction::whereNotNull('last_synced_at')
                    ->orderBy('last_synced_at', 'desc')
                    ->value('last_synced_at'),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar transacciones
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,json',
            'transaction_type' => 'nullable|string',
            'status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Transaction::query();

            if ($request->transaction_type) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->date_from) {
                $query->whereDate('binance_create_time', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->whereDate('binance_create_time', '<=', $request->date_to);
            }

            $transactions = $query->orderBy('binance_create_time', 'desc')->get();

            if ($request->format === 'csv') {
                // Implementar exportación CSV
                $csvData = $this->generateCsv($transactions);
                
                return response()->json([
                    'success' => true,
                    'data' => $csvData,
                    'format' => 'csv',
                    'filename' => 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv'
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => $transactions,
                    'format' => 'json',
                    'filename' => 'transactions_' . now()->format('Y-m-d_H-i-s') . '.json'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateCsv($transactions): string
    {
        $headers = [
            'ID', 'Order Number', 'Transaction Type', 'Asset Type', 'Fiat Type',
            'Order Type', 'Quantity', 'Price', 'Total Price', 'Status',
            'Create Time', 'Notes', 'Payment Method', 'Counter Party'
        ];

        $csv = implode(',', $headers) . "\n";

        foreach ($transactions as $transaction) {
            $row = [
                $transaction->id,
                $transaction->order_number,
                $transaction->transaction_type,
                $transaction->asset_type,
                $transaction->fiat_type,
                $transaction->order_type,
                $transaction->quantity,
                $transaction->price,
                $transaction->total_price,
                $transaction->status,
                $transaction->binance_create_time?->format('Y-m-d H:i:s'),
                $transaction->notes,
                $transaction->payment_method,
                $transaction->counter_party,
            ];

            $csv .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field ?? '') . '"';
            }, $row)) . "\n";
        }

        return $csv;
    }
}