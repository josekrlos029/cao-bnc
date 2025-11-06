<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\CounterParty;
use App\Jobs\SyncBinanceTransactions;
use App\Jobs\SyncBybitTransactions;
use App\Jobs\SyncOKXTransactions;
use App\Exports\TransactionsExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionController extends Controller
{
    /**
     * Mostrar el dashboard de transacciones
     */
    public function index(Request $request): Response
    {
        $userId = auth()->id();
        
        // Query base filtrado por usuario autenticado
        $query = Transaction::where('user_id', $userId);

        // Filtros
        if ($request->filled('exchange')) {
            $query->where('exchange', $request->exchange);
        }

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

        // Búsqueda por número de orden
        if ($request->filled('search')) {
            $query->where('order_number', 'like', '%' . $request->search . '%');
        }

        // Ordenamiento: siempre por fecha de la orden descendente (más recientes primero)
        $query->orderBy('binance_create_time', 'desc');

        $transactions = $query->paginate(50)->appends($request->query());

        // Estadísticas filtradas por usuario
        $userTransactionsQuery = Transaction::where('user_id', $userId);
        $stats = [
            'total_transactions' => (clone $userTransactionsQuery)->count(),
            'completed_transactions' => (clone $userTransactionsQuery)->completed()->count(),
            'pending_transactions' => (clone $userTransactionsQuery)->where('status', 'pending')->count(),
            'manual_entries' => (clone $userTransactionsQuery)->manualEntries()->count(),
            'total_value_usdt' => (clone $userTransactionsQuery)->completed()->sum('total_price'),
            'last_sync' => (clone $userTransactionsQuery)->whereNotNull('last_synced_at')
                ->orderBy('last_synced_at', 'desc')
                ->value('last_synced_at'),
        ];

        // Estadísticas de enriquecimiento (solo para transacciones P2P)
        $p2pQuery = (clone $userTransactionsQuery)->where('transaction_type', 'p2p_order');
        $totalP2P = (int) $p2pQuery->count();
        
        // Verificar si la columna enrichment_status existe antes de usarla
        $enrichmentStats = [
            'total_p2p' => $totalP2P,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'not_started' => 0,
        ];
        
        if (Schema::hasColumn('transactions', 'enrichment_status')) {
            $enrichmentStats['pending'] = (int) $p2pQuery->where('enrichment_status', 'pending')->count();
            $enrichmentStats['processing'] = (int) $p2pQuery->where('enrichment_status', 'processing')->count();
            $enrichmentStats['completed'] = (int) $p2pQuery->where('enrichment_status', 'completed')->count();
            $enrichmentStats['failed'] = (int) $p2pQuery->where('enrichment_status', 'failed')->count();
            $enrichmentStats['not_started'] = (int) $p2pQuery->whereNull('enrichment_status')->count();
        } else {
            // Si la columna no existe, todas las transacciones están "sin iniciar"
            $enrichmentStats['not_started'] = $totalP2P;
        }

        // Calcular porcentaje de progreso
        $totalEnrichable = $enrichmentStats['total_p2p'];
        $enriched = $enrichmentStats['completed'];
        $progressPercentage = $totalEnrichable > 0 
            ? round(($enriched / $totalEnrichable) * 100, 2) 
            : 100;
        
        $enrichmentStats['progress_percentage'] = (float) $progressPercentage;
        $enrichmentStats['has_active_enrichment'] = ($enrichmentStats['pending'] + $enrichmentStats['processing']) > 0;

        // Opciones para filtros (solo del usuario)
        $filterOptions = [
            'exchanges' => Transaction::where('user_id', $userId)->distinct()->pluck('exchange')->filter()->values(),
            'transaction_types' => Transaction::where('user_id', $userId)->distinct()->pluck('transaction_type')->filter()->values(),
            'statuses' => Transaction::where('user_id', $userId)->distinct()->pluck('status')->filter()->values(),
            'asset_types' => Transaction::where('user_id', $userId)->distinct()->pluck('asset_type')->filter()->values(),
            'fiat_types' => Transaction::where('user_id', $userId)->distinct()->pluck('fiat_type')->filter()->values(),
        ];

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'enrichmentStats' => $enrichmentStats,
            'filterOptions' => $filterOptions,
            'filters' => $request->only(['exchange', 'transaction_type', 'status', 'asset_type', 'fiat_type', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * Mostrar detalles de una transacción
     */
    public function show(Transaction $transaction): Response
    {
        // Verificar que la transacción pertenezca al usuario autenticado
        if ($transaction->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Crear nueva transacción manual
     */
    public function create(): Response
    {
        return Inertia::render('Transactions/Create');
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
                'exchange' => $request->exchange ?? 'binance',
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
     * Permitir edición de todas las transacciones (no solo manuales)
     * para editar dni_type y counter_party_dni
     */
    public function edit(Transaction $transaction): Response
    {
        // Verificar que la transacción pertenezca al usuario autenticado
        if ($transaction->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        
        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Actualizar transacción
     * Permite editar dni_type y counter_party_dni en todas las transacciones
     * Para otros campos, solo permite editar si es manual_entry
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        // Verificar que la transacción pertenezca al usuario autenticado
        if ($transaction->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Validación para dni_type y counter_party_dni (permitido en todas las transacciones)
        $validator = Validator::make($request->all(), [
            'dni_type' => 'nullable|string|max:50|in:CC,CE,PASSPORT,NIT,TI,RUT,OTRO',
            'counter_party_dni' => 'nullable|string|max:255',
        ]);

        // Si es una transacción manual, validar todos los campos
        if ($transaction->is_manual_entry) {
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
                'dni_type' => 'nullable|string|max:50|in:CC,CE,PASSPORT,NIT,TI,RUT,OTRO',
                'counter_party_dni' => 'nullable|string|max:255',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];
            
            // Si es manual entry, permitir actualizar todos los campos
            if ($transaction->is_manual_entry) {
                $updateData = [
                    'order_number' => $request->order_number,
                    'transaction_type' => $request->transaction_type,
                    'exchange' => $request->exchange ?? $transaction->exchange ?? 'binance',
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
                ];
            }
            
            // Siempre permitir actualizar dni_type y counter_party_dni
            if ($request->has('dni_type')) {
                $updateData['dni_type'] = $request->dni_type;
            }
            
            if ($request->has('counter_party_dni')) {
                $updateData['counter_party_dni'] = $request->counter_party_dni;
            }
            
            // Si se proporciona counter_party, buscar/crear CounterParty y actualizar sus datos
            if ($request->filled('counter_party') || $request->filled('dni_type') || $request->filled('counter_party_dni')) {
                $counterPartyName = $request->counter_party ?? $transaction->counter_party;
                $exchange = $transaction->exchange ?? 'binance';
                
                if ($counterPartyName && auth()->id()) {
                    $counterParty = CounterParty::findOrCreateForTransaction(
                        auth()->id(),
                        $exchange,
                        $counterPartyName
                    );
                    
                    // Actualizar datos del CounterParty si se proporcionan
                    $counterPartyUpdate = [];
                    if ($request->filled('dni_type')) {
                        $counterPartyUpdate['dni_type'] = $request->dni_type;
                    }
                    if ($request->filled('counter_party_dni')) {
                        $counterPartyUpdate['counter_party_dni'] = $request->counter_party_dni;
                    }
                    
                    if (!empty($counterPartyUpdate)) {
                        $counterParty->update($counterPartyUpdate);
                    }
                }
            }
            
            $transaction->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'transaction' => $transaction->fresh()
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
            // Verificar que la transacción pertenezca al usuario autenticado
            if ($transaction->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            // Solo permitir eliminar entradas manuales
            if (!$transaction->is_manual_entry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar entradas manuales'
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Usar siempre el usuario autenticado para seguridad
            $userId = auth()->id();
            $startTime = null;
            $endTime = null;

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

            // Verificar que el usuario tenga credenciales activas de al menos un exchange
            $hasBinanceCredentials = \App\Models\BinanceCredential::where('user_id', $userId)
                ->where('is_active', true)
                ->exists();
            
            $hasBybitCredentials = \App\Models\BybitCredential::where('user_id', $userId)
                ->where('is_active', true)
                ->exists();

            $hasOKXCredentials = \App\Models\OKXCredential::where('user_id', $userId)
                ->where('is_active', true)
                ->exists();

            if (!$hasBinanceCredentials && !$hasBybitCredentials && !$hasOKXCredentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes credenciales de ningún exchange configuradas. Por favor, configura tus credenciales primero.'
                ], 400);
            }

            // En modo desarrollo, ejecutar sincrónicamente para pruebas más rápidas
            // En producción, usar dispatch() para ejecutar en background
            if (app()->environment('local')) {
                try {
                    $syncedExchanges = [];
                    
                    if ($hasBinanceCredentials) {
                        SyncBinanceTransactions::dispatchSync($startTime, $endTime, $userId);
                        $syncedExchanges[] = 'Binance';
                    }
                    
                    if ($hasBybitCredentials) {
                        SyncBybitTransactions::dispatchSync($startTime, $endTime, $userId);
                        $syncedExchanges[] = 'Bybit';
                    }
                    
                    if ($hasOKXCredentials) {
                        SyncOKXTransactions::dispatchSync($startTime, $endTime, $userId);
                        $syncedExchanges[] = 'OKX';
                    }
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Sincronización completada. Las transacciones se han actualizado.',
                        'synced_exchanges' => $syncedExchanges,
                        'sync_period' => [
                            'start' => $startTime->format('Y-m-d H:i:s'),
                            'end' => $endTime->format('Y-m-d H:i:s')
                        ]
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error durante la sincronización: ' . $e->getMessage()
                    ], 500);
                }
            } else {
                $syncedExchanges = [];
                
                if ($hasBinanceCredentials) {
                    SyncBinanceTransactions::dispatch($startTime, $endTime, $userId);
                    $syncedExchanges[] = 'Binance';
                }
                
                if ($hasBybitCredentials) {
                    SyncBybitTransactions::dispatch($startTime, $endTime, $userId);
                    $syncedExchanges[] = 'Bybit';
                }

                if ($hasOKXCredentials) {
                    SyncOKXTransactions::dispatch($startTime, $endTime, $userId);
                    $syncedExchanges[] = 'OKX';
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Sincronización iniciada correctamente. Las transacciones aparecerán en unos momentos.',
                    'synced_exchanges' => $syncedExchanges,
                    'sync_period' => [
                        'start' => $startTime->format('Y-m-d H:i:s'),
                        'end' => $endTime->format('Y-m-d H:i:s')
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de transacciones
     */
    public function stats(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $userTransactionsQuery = Transaction::where('user_id', $userId);
            
            $stats = [
                'total_transactions' => (clone $userTransactionsQuery)->count(),
                'completed_transactions' => (clone $userTransactionsQuery)->completed()->count(),
                'pending_transactions' => (clone $userTransactionsQuery)->where('status', 'pending')->count(),
                'manual_entries' => (clone $userTransactionsQuery)->manualEntries()->count(),
                'by_type' => (clone $userTransactionsQuery)->selectRaw('transaction_type, COUNT(*) as count')
                    ->groupBy('transaction_type')
                    ->pluck('count', 'transaction_type'),
                'by_status' => (clone $userTransactionsQuery)->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'by_asset' => (clone $userTransactionsQuery)->selectRaw('asset_type, COUNT(*) as count')
                    ->groupBy('asset_type')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->pluck('count', 'asset_type'),
                'total_value' => (clone $userTransactionsQuery)->completed()->sum('total_price'),
                'last_sync' => (clone $userTransactionsQuery)->whereNotNull('last_synced_at')
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
            $userId = auth()->id();
            $query = Transaction::where('user_id', $userId);

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

    /**
     * Exportar transacciones a Excel
     * Aplica los mismos filtros que el método index
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        try {
            $userId = auth()->id();
            
            // Query base filtrado por usuario autenticado (mismo que en index)
            $query = Transaction::where('user_id', $userId);

            // Aplicar los mismos filtros que el método index
            if ($request->filled('exchange')) {
                $query->where('exchange', $request->exchange);
            }

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

            // Búsqueda por número de orden
            if ($request->filled('search')) {
                $query->where('order_number', 'like', '%' . $request->search . '%');
            }

            // Ordenamiento: siempre por fecha de la orden descendente (más recientes primero)
            $query->orderBy('binance_create_time', 'desc');

            // Obtener todas las transacciones (sin paginación)
            $transactions = $query->get();

            $fileName = 'transacciones_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download(new TransactionsExport($transactions), $fileName);

        } catch (\Exception $e) {
            abort(500, 'Error al exportar transacciones: ' . $e->getMessage());
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