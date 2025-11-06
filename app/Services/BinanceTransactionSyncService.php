<?php

namespace App\Services;

use App\Models\BinanceCredential;
use App\Models\Transaction;
use App\Models\CounterParty;
use App\Jobs\EnrichTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BinanceTransactionSyncService
{
    private const BASE_URL = 'https://api.binance.com';
    private const TESTNET_BASE_URL = 'https://testnet.binance.vision';
    private const SAPI_BASE_URL = 'https://api.binance.com/sapi/v1';
    
    private string $apiKey;
    private string $secretKey;
    private bool $isTestnet;
    private string $baseUrl;
    private string $sapiBaseUrl;
    private ?int $userId;

    public function __construct(BinanceCredential $credentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->isTestnet = $credentials->is_testnet;
            $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
            $this->sapiBaseUrl = $this->isTestnet ? 'https://testnet.binance.vision/sapi/v1' : self::SAPI_BASE_URL;
            $this->userId = $credentials->user_id;
        } else {
            $this->userId = null;
        }
    }

    /**
     * Sincronizar todas las transacciones desde múltiples endpoints
     */
    public function syncAllTransactions(Carbon $startTime = null, Carbon $endTime = null): array
    {
        $results = [];
        
        if (!$startTime) {
            $startTime = now()->subDays(30); // Últimos 30 días por defecto
        }
        
        if (!$endTime) {
            $endTime = now();
        }

        try {
            // 1. Sincronizar trades spot
            //$results['spot_trades'] = $this->syncSpotTrades($startTime, $endTime);
            
            // 2. Sincronizar órdenes P2P
            $results['p2p_orders'] = $this->syncP2POrders($startTime, $endTime);
            
            // 3. Sincronizar depósitos
            //$results['deposits'] = $this->syncDeposits($startTime, $endTime);
            
            // 4. Sincronizar retiros
            //$results['withdrawals'] = $this->syncWithdrawals($startTime, $endTime);
            
            // 5. Sincronizar transacciones de Binance Pay
            //$results['pay_transactions'] = $this->syncPayTransactions($startTime, $endTime);
            
            // 6. Sincronizar transferencias internas
            //$results['internal_transfers'] = $this->syncInternalTransfers($startTime, $endTime);
            
            Log::info('Binance Transaction Sync Completed', [
                'results' => $results,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Binance Transaction Sync Failed', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            
            throw $e;
        }
    }

    /**
     * Sincronizar trades spot (/api/v3/myTrades)
     * Nota: La API de Binance solo permite consultar máximo 24 horas por petición
     */
    public function syncSpotTrades(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(7);
        $endTime = $endTime ?? now();

        try {
            // Obtener símbolos donde el usuario tiene trades (optimización)
            // Primero intentamos obtener los símbolos más comunes/usados
            $symbols = $this->getRelevantTradingPairs($startTime, $endTime);
            
            $totalSymbols = count($symbols);
            $processedSymbols = 0;
            
            Log::info('Starting spot trades sync', [
                'symbols_count' => $totalSymbols,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s')
            ]);
            
            foreach ($symbols as $symbol) {
                $processedSymbols++;
                
                // Dividir el rango de tiempo en chunks de máximo 23 horas (para estar seguros)
                $timeChunks = $this->splitTimeRange($startTime, $endTime, 23);
                
                foreach ($timeChunks as $chunk) {
                    $chunkStart = $chunk['start'];
                    $chunkEnd = $chunk['end'];
                    
                    try {
                        $trades = $this->getSpotTrades($symbol, $chunkStart, $chunkEnd);
                        
                        foreach ($trades as $trade) {
                            $transaction = Transaction::updateOrCreate(
                                [
                                    'trade_id' => $trade['id'],
                                    'transaction_type' => 'spot_trade',
                                    'user_id' => $this->userId,
                                ],
                                [
                                    'user_id' => $this->userId,
                                    'exchange' => 'binance',
                                    'order_number' => $trade['orderId'],
                                    'asset_type' => $this->extractBaseAsset($symbol),
                                    'fiat_type' => $this->extractQuoteAsset($symbol),
                                    'order_type' => $trade['isBuyer'] ? 'BUY' : 'SELL',
                                    'quantity' => $trade['qty'],
                                    'price' => $trade['price'],
                                    'total_price' => $trade['quoteQty'],
                                    'commission' => $trade['commission'],
                                    'status' => 'completed',
                                    'binance_create_time' => Carbon::createFromTimestamp($trade['time'] / 1000),
                                    'source_endpoint' => '/api/v3/myTrades',
                                    'metadata' => $trade,
                                    'last_synced_at' => now(),
                                ]
                            );
                            
                            if ($transaction->wasRecentlyCreated) {
                                $synced++;
                            }
                        }
                        
                        // Rate limiting: delay entre chunks del mismo símbolo
                        if (count($timeChunks) > 1) {
                            usleep(200000); // 200ms entre chunks
                        }
                        
                    } catch (\Exception $e) {
                        Log::warning('Error fetching spot trades for symbol', [
                            'symbol' => $symbol,
                            'chunk_start' => $chunkStart->format('Y-m-d H:i:s'),
                            'chunk_end' => $chunkEnd->format('Y-m-d H:i:s'),
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
                
                // Rate limiting: delay significativo entre símbolos para evitar ban de IP
                // Binance permite ~1200 requests por minuto, pero con seguridad usamos máximo 10 requests/min
                // = 6 segundos entre símbolos (conservador para evitar ban)
                if ($processedSymbols < $totalSymbols) {
                    usleep(6000000); // 6 segundos entre símbolos para ser muy conservador
                }
                
                // Log de progreso cada 10 símbolos
                if ($processedSymbols % 10 === 0) {
                    Log::info('Spot trades sync progress', [
                        'processed' => $processedSymbols,
                        'total' => $totalSymbols,
                        'synced_so_far' => $synced
                    ]);
                }
            }
            
            Log::info('Spot trades sync completed', [
                'total_symbols' => $totalSymbols,
                'synced_transactions' => $synced
            ]);
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing spot trades', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Sincronizar órdenes P2P (/sapi/v1/c2c/orderMatch/listUserOrderHistory)
     */
    public function syncP2POrders(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(30);
        $endTime = $endTime ?? now();

        try {
            $orders = $this->getP2POrderHistory($startTime, $endTime);
            
            foreach ($orders as $order) {
                try {
                    // Extraer campos principales de la respuesta del API
                    $orderNumber = $order['orderNumber'] ?? null;
                    $tradeType = $order['tradeType'] ?? $order['orderType'] ?? null;
                    
                    if (!$orderNumber) {
                        Log::warning('P2P order missing orderNumber', ['order' => $order]);
                        continue;
                    }
                    
                    // Extraer payment_method de diferentes posibles ubicaciones en la respuesta
                    // payMethodName es el campo real que viene en la API de Binance
                    $paymentMethod = $order['payMethodName'] 
                        ?? $order['paymentMethod'] 
                        ?? $order['payment_method'] 
                        ?? $order['payMethod'] 
                        ?? $order['paymentType'] 
                        ?? (isset($order['payMethods']) && is_array($order['payMethods']) && count($order['payMethods']) > 0 
                            ? $order['payMethods'][0]['name'] ?? $order['payMethods'][0]['payMethodName'] ?? $order['payMethods'][0] ?? null 
                            : null)
                        ?? null;

                    // Si es un array, intentar extraer el nombre
                    if (is_array($paymentMethod)) {
                        $paymentMethod = $paymentMethod['payMethodName'] 
                            ?? $paymentMethod['name'] 
                            ?? $paymentMethod['paymentMethod'] 
                            ?? null;
                    }

                    // Obtener advertisement_order_number para el detalle
                    $adOrderNo = $order['advertisementOrderNumber'] ?? $order['advNo'] ?? null;

                    $transaction = Transaction::updateOrCreate(
                        [
                            'order_number' => $orderNumber,
                            'transaction_type' => 'p2p_order',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'binance',
                            'advertisement_order_number' => $adOrderNo,
                            'asset_type' => $order['asset'] ?? null,
                            'fiat_type' => $order['fiat'] ?? null,
                            'order_type' => $tradeType, // BUY o SELL
                            'quantity' => $order['amount'] ?? 0, // Cantidad del activo
                            'price' => $order['unitPrice'] ?? 0, // Precio unitario
                            'total_price' => $order['totalPrice'] ?? 0, // Precio total
                            'commission' => $order['commission'] ?? 0,
                            'payment_method' => $paymentMethod,
                            // counter_party se establecerá en enrichP2POrderWithDetail con el nickname correcto del enrich
                            // 'counter_party' => $order['counterPartNickName'] ?? $order['counterpartNickName'] ?? $order['counterPartyNickName'] ?? null,
                            'status' => $this->mapP2PStatus($order['orderStatus'] ?? 'PENDING'),
                            'binance_create_time' => isset($order['createTime']) 
                                ? Carbon::createFromTimestamp($order['createTime'] / 1000) 
                                : now(),
                            'binance_update_time' => isset($order['updateTime']) 
                                ? Carbon::createFromTimestamp($order['updateTime'] / 1000) 
                                : (isset($order['createTime']) 
                                    ? Carbon::createFromTimestamp($order['createTime'] / 1000) 
                                    : now()),
                            'source_endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory',
                            'metadata' => $order,
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    // Despachar job para enriquecimiento en background
                    // El enriquecimiento se hace de forma asíncrona para no bloquear la sincronización
                    if ($orderNumber && $adOrderNo) {
                        try {
                            // Marcar transacción como pending para enriquecimiento
                            if (Schema::hasColumn('transactions', 'enrichment_status')) {
                                $transaction->update(['enrichment_status' => 'pending']);
                            }
                            
                            EnrichTransaction::dispatch($transaction->id);
                            Log::debug('EnrichTransaction job dispatched for Binance transaction', [
                                'transaction_id' => $transaction->id,
                                'order_number' => $orderNumber,
                                'ad_order_no' => $adOrderNo
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Error dispatching EnrichTransaction job', [
                                'transaction_id' => $transaction->id,
                                'order_number' => $orderNumber,
                                'ad_order_no' => $adOrderNo,
                                'error' => $e->getMessage()
                            ]);
                            // Continuar aunque falle el despacho del job
                        }
                    } else {
                        Log::debug('P2P order missing orderNumber or adOrderNo, skipping enrichment', [
                            'order_number' => $orderNumber,
                            'ad_order_no' => $adOrderNo
                        ]);
                    }
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing individual P2P order', [
                        'order' => $order,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing P2P orders', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            return 0;
        }
    }

    /**
     * Sincronizar depósitos (/sapi/v1/capital/deposit/hisrec)
     */
    public function syncDeposits(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(30);
        $endTime = $endTime ?? now();

        try {
            $deposits = $this->getDepositHistory($startTime, $endTime);
            
            foreach ($deposits as $deposit) {
                try {
                    // Usar txId como order_number si existe, sino usar el id del depósito
                    $orderNumber = $deposit['txId'] ?? $deposit['id'] ?? 'deposit_' . ($deposit['insertTime'] ?? time());
                    
                    $transaction = Transaction::updateOrCreate(
                        [
                            'transaction_id' => $deposit['txId'] ?? null,
                            'transaction_type' => 'deposit',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'binance',
                            'order_number' => $orderNumber,
                            'asset_type' => $deposit['coin'] ?? 'UNKNOWN',
                            'quantity' => is_numeric($deposit['amount']) ? (float) $deposit['amount'] : 0,
                            'status' => $this->mapDepositStatus($deposit['status'] ?? 0),
                            'network_fee' => isset($deposit['networkFee']) && is_numeric($deposit['networkFee']) ? (float) $deposit['networkFee'] : 0,
                            'binance_create_time' => isset($deposit['insertTime']) ? Carbon::createFromTimestamp($deposit['insertTime'] / 1000) : now(),
                            'source_endpoint' => '/sapi/v1/capital/deposit/hisrec',
                            'metadata' => $deposit,
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Error processing individual deposit', [
                        'deposit' => $deposit,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing deposits', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Sincronizar retiros (/sapi/v1/capital/withdraw/history)
     */
    public function syncWithdrawals(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(30);
        $endTime = $endTime ?? now();

        try {
            $withdrawals = $this->getWithdrawalHistory($startTime, $endTime);
            
            foreach ($withdrawals as $withdrawal) {
                try {
                    // Usar id como order_number si existe
                    $orderNumber = $withdrawal['id'] ?? 'withdrawal_' . ($withdrawal['applyTime'] ?? time());
                    
                    // Validar y convertir valores numéricos
                    $amount = isset($withdrawal['amount']) && is_numeric($withdrawal['amount']) 
                        ? (float) $withdrawal['amount'] 
                        : 0;
                    
                    $transactionFee = isset($withdrawal['transactionFee']) && is_numeric($withdrawal['transactionFee'])
                        ? (float) $withdrawal['transactionFee']
                        : 0;
                    
                    // Convertir status a int si es string
                    $statusInt = isset($withdrawal['status']) 
                        ? (is_numeric($withdrawal['status']) ? (int) $withdrawal['status'] : 0)
                        : 0;
                    
                    // Procesar applyTime - puede ser timestamp (int) o string de fecha
                    $binanceCreateTime = now();
                    if (isset($withdrawal['applyTime'])) {
                        if (is_numeric($withdrawal['applyTime'])) {
                            // Es un timestamp en milisegundos
                            $binanceCreateTime = Carbon::createFromTimestamp($withdrawal['applyTime'] / 1000);
                        } else {
                            // Es un string de fecha, intentar parsearlo
                            try {
                                $binanceCreateTime = Carbon::parse($withdrawal['applyTime']);
                            } catch (\Exception $e) {
                                Log::warning('Could not parse applyTime for withdrawal', [
                                    'applyTime' => $withdrawal['applyTime'],
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                    
                    $transaction = Transaction::updateOrCreate(
                        [
                            'transaction_id' => $withdrawal['id'] ?? null,
                            'transaction_type' => 'withdrawal',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'binance',
                            'order_number' => $orderNumber,
                            'asset_type' => $withdrawal['coin'] ?? 'UNKNOWN',
                            'quantity' => $amount,
                            'status' => $this->mapWithdrawalStatus($statusInt),
                            'network_fee' => $transactionFee,
                            'binance_create_time' => $binanceCreateTime,
                            'source_endpoint' => '/sapi/v1/capital/withdraw/history',
                            'metadata' => $withdrawal,
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Error processing individual withdrawal', [
                        'withdrawal' => $withdrawal,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing withdrawals', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Sincronizar transacciones de Binance Pay (/sapi/v1/pay/transactions)
     */
    public function syncPayTransactions(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(30);
        $endTime = $endTime ?? now();

        try {
            $payTransactions = $this->getPayTransactions($startTime, $endTime);
            
            foreach ($payTransactions as $payTx) {
                try {
                    // Usar transactionId como order_number si existe
                    $orderNumber = $payTx['transactionId'] ?? 'pay_' . ($payTx['createTime'] ?? time());
                    
                    $transaction = Transaction::updateOrCreate(
                        [
                            'transaction_id' => $payTx['transactionId'] ?? null,
                            'transaction_type' => 'pay_transaction',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'binance',
                            'order_number' => $orderNumber,
                            'asset_type' => $payTx['currency'] ?? 'UNKNOWN',
                            'quantity' => isset($payTx['amount']) && is_numeric($payTx['amount']) ? (float) $payTx['amount'] : 0,
                            'status' => $this->mapPayStatus($payTx['status'] ?? 'PENDING'),
                            'binance_create_time' => isset($payTx['createTime']) ? Carbon::createFromTimestamp($payTx['createTime'] / 1000) : now(),
                            'source_endpoint' => '/sapi/v1/pay/transactions',
                            'metadata' => $payTx,
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Error processing individual pay transaction', [
                        'payTx' => $payTx,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing pay transactions', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Sincronizar transferencias internas (/sapi/v1/asset/transfer)
     */
    public function syncInternalTransfers(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(30);
        $endTime = $endTime ?? now();

        try {
            $transfers = $this->getInternalTransfers($startTime, $endTime);
            
            // Validar que la respuesta sea un array
            if (!is_array($transfers)) {
                Log::warning('Internal transfers response is not an array', [
                    'response_type' => gettype($transfers),
                    'response' => $transfers
                ]);
                return 0;
            }
            
            foreach ($transfers as $transfer) {
                try {
                    // Validar que $transfer sea un array
                    if (!is_array($transfer)) {
                        Log::warning('Transfer item is not an array', [
                            'transfer_type' => gettype($transfer),
                            'transfer' => $transfer
                        ]);
                        continue;
                    }
                    
                    // Usar tranId como order_number si existe
                    $orderNumber = $transfer['tranId'] ?? 'transfer_' . ($transfer['timestamp'] ?? time());
                    
                    $transaction = Transaction::updateOrCreate(
                        [
                            'transaction_id' => $transfer['tranId'] ?? null,
                            'transaction_type' => 'internal_transfer',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'binance',
                            'order_number' => $orderNumber,
                            'asset_type' => $transfer['asset'] ?? 'UNKNOWN',
                            'quantity' => isset($transfer['amount']) && is_numeric($transfer['amount']) ? (float) $transfer['amount'] : 0,
                            'status' => $this->mapTransferStatus($transfer['status'] ?? 'PENDING'),
                            'binance_create_time' => isset($transfer['timestamp']) ? Carbon::createFromTimestamp($transfer['timestamp'] / 1000) : now(),
                            'source_endpoint' => '/sapi/v1/asset/transfer',
                            'metadata' => $transfer,
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Error processing individual internal transfer', [
                        'transfer' => $transfer,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing internal transfers', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    // Métodos privados para hacer requests a la API

    private function getSpotTrades(string $symbol, Carbon $startTime, Carbon $endTime): array
    {
        try {
            // Validar que el rango no exceda 24 horas
            $hoursDiff = $startTime->diffInHours($endTime);
            if ($hoursDiff > 24) {
                Log::warning('Time range exceeds 24 hours for spot trades', [
                    'symbol' => $symbol,
                    'hours_diff' => $hoursDiff,
                    'start_time' => $startTime->format('Y-m-d H:i:s'),
                    'end_time' => $endTime->format('Y-m-d H:i:s')
                ]);
                // Ajustar automáticamente a 24 horas
                $endTime = $startTime->copy()->addHours(23)->addMinutes(59)->addSeconds(59);
            }
            
            $response = $this->makeAuthenticatedRequest('GET', '/api/v3/myTrades', [
                'symbol' => $symbol,
                'startTime' => $startTime->timestamp * 1000,
                'endTime' => $endTime->timestamp * 1000,
                'limit' => 1000
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return is_array($data) ? $data : [];
            }

            // Si es el error específico de "More than 24 hours", loguearlo pero no fallar
            $responseBody = $response->body();
            $is24HourError = str_contains($responseBody, 'More than 24 hours') || 
                           str_contains($responseBody, '-1127');

            if ($is24HourError) {
                Log::warning('Binance API: Time range too large for spot trades (should be handled by splitTimeRange)', [
                    'symbol' => $symbol,
                    'status' => $response->status(),
                    'response' => $responseBody,
                    'start_time' => $startTime->format('Y-m-d H:i:s'),
                    'end_time' => $endTime->format('Y-m-d H:i:s'),
                    'hours_diff' => $hoursDiff
                ]);
            } else {
                Log::warning('Binance API returned unsuccessful response for spot trades', [
                    'symbol' => $symbol,
                    'status' => $response->status(),
                    'response' => $responseBody
                ]);
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('Error fetching spot trades from Binance API', [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function getP2POrderHistory(Carbon $startTime, Carbon $endTime): array
    {
        $allOrders = [];
        
        try {
            $page = 1;
            $hasMore = true;
            
            while ($hasMore) {
                // NOTA: Este endpoint NO requiere 'tradeType', 'rows' ni 'recvWindow'
                // El tradeType viene en el response de cada orden
                $requestParams = [
                    'startTime' => (int) ($startTime->timestamp * 1000), // Nota: startTime, no startTimestamp
                    'endTime' => (int) ($endTime->timestamp * 1000), // Nota: endTime, no endTimestamp
                    'page' => (int) $page,
                ];
                
                $response = $this->makeP2PRequest('/sapi/v1/c2c/orderMatch/listUserOrderHistory', $requestParams);

                if ($response->successful()) {
                    $data = $response->json();
                    $responseBody = $response->body();
                    
                    // Log detallado de la respuesta exitosa
                    Log::info('Binance P2P Order History Response - SUCCESS', [
                        'endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory',
                        'page' => $page,
                        'request_params' => $requestParams,
                        'response_status' => $response->status(),
                        'response_headers' => $response->headers(),
                        'response_body' => $responseBody,
                        'response_json' => $data,
                        'orders_count' => isset($data['data']) && is_array($data['data']) ? count($data['data']) : 0,
                        'total_orders' => $data['total'] ?? null,
                        'timestamp' => now()->toIso8601String()
                    ]);
                    
                    // Guardar también en un archivo específico para facilitar el análisis
                    $this->logP2PResponseToFile($page, $requestParams, $responseBody, $data);
                    
                    if (isset($data['data']) && is_array($data['data'])) {
                        $orders = $data['data'];
                        $allOrders = array_merge($allOrders, $orders);
                        
                        // Verificar si hay más páginas
                        // Si no hay datos o el array está vacío, significa que es la última página
                        $hasMore = !empty($orders);
                        $page++;
                    } else {
                        $hasMore = false;
                    }
                } else {
                    $responseBody = $response->body();
                    
                    Log::warning('Binance API returned unsuccessful response for P2P orders', [
                        'endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory',
                        'page' => $page,
                        'request_params' => $requestParams,
                        'status' => $response->status(),
                        'response_headers' => $response->headers(),
                        'response_body' => $responseBody,
                        'timestamp' => now()->toIso8601String()
                    ]);
                    
                    // Guardar también respuestas fallidas en el archivo
                    $this->logP2PResponseToFile($page, $requestParams, $responseBody, null, false);
                    
                    $hasMore = false;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error fetching P2P orders from Binance API', [
                'endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);
        }
        
        return $allOrders;
    }

    /**
     * Obtener detalle de una orden P2P usando el endpoint POST /sapi/v1/c2c/orderMatch/getUserOrderDetail
     */
    private function getP2POrderDetail(string $adOrderNo): ?array
    {
        try {
            $requestBody = [
                'adOrderNo' => $adOrderNo
            ];
            
            $response = $this->makeP2PPostRequest('/sapi/v1/c2c/orderMatch/getUserOrderDetail', $requestBody);

            $responseBody = $response->body();
            $responseData = $response->json();
            $responseStatus = $response->status();
            
            // Guardar response en archivo de log para análisis detallado
            $this->logP2POrderDetailResponseToFile($adOrderNo, $requestBody, $responseBody, $responseData, $responseStatus);
            
            // Log detallado del response completo para análisis
            Log::info('Binance P2P Order Detail Response', [
                'endpoint' => '/sapi/v1/c2c/orderMatch/getUserOrderDetail',
                'adOrderNo' => $adOrderNo,
                'request_body' => $requestBody,
                'response_status' => $responseStatus,
                'response_headers' => $response->headers(),
                'response_body_raw' => $responseBody,
                'response_json' => $responseData,
                'response_success' => isset($responseData['success']) ? $responseData['success'] : null,
                'response_code' => isset($responseData['code']) ? $responseData['code'] : null,
                'response_message' => isset($responseData['message']) ? $responseData['message'] : null,
                'has_data' => isset($responseData['data']),
                'data_keys' => isset($responseData['data']) && is_array($responseData['data']) 
                    ? array_keys($responseData['data']) 
                    : null,
                'timestamp' => now()->toIso8601String()
            ]);

            if ($response->successful()) {
                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data'])) {
                    // Log adicional con los campos disponibles en el detalle
                    $orderDetail = $responseData['data'];
                    Log::info('Binance P2P Order Detail - Available Fields', [
                        'adOrderNo' => $adOrderNo,
                        'available_fields' => array_keys($orderDetail),
                        'tradeType' => $orderDetail['tradeType'] ?? null,
                        'orderStatus' => $orderDetail['orderStatus'] ?? null,
                        'has_idNumber' => isset($orderDetail['idNumber']),
                        'has_buyerInfo' => isset($orderDetail['buyerName']) || isset($orderDetail['buyerNickname']) || isset($orderDetail['buyerMobilePhone']),
                        'has_sellerInfo' => isset($orderDetail['sellerName']) || isset($orderDetail['sellerNickname']) || isset($orderDetail['sellerMobilePhone']),
                    ]);
                    
                    return $orderDetail;
                }
                
                Log::warning('Binance P2P Order Detail response missing data', [
                    'adOrderNo' => $adOrderNo,
                    'response' => $responseData
                ]);
                
                return null;
            }

            Log::warning('Binance API returned unsuccessful response for P2P order detail', [
                'endpoint' => '/sapi/v1/c2c/orderMatch/getUserOrderDetail',
                'adOrderNo' => $adOrderNo,
                'status' => $responseStatus,
                'response' => $responseBody
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error fetching P2P order detail from Binance API', [
                'endpoint' => '/sapi/v1/c2c/orderMatch/getUserOrderDetail',
                'adOrderNo' => $adOrderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Enriquecer una transacción P2P con información adicional del detalle de la orden
     * 
     * Cuando tradeType es "BUY": el usuario es comprador, la contraparte es el vendedor (seller)
     * Cuando tradeType es "SELL": el usuario es vendedor, la contraparte es el comprador (buyer)
     */
    public function enrichP2POrderWithDetail(Transaction $transaction, string $adOrderNo, ?string $tradeType): void
    {
        $orderDetail = $this->getP2POrderDetail($adOrderNo);
        
        if (!$orderDetail) {
            Log::warning('EnrichP2POrderWithDetail: No order detail returned from API', [
                'transaction_id' => $transaction->id,
                'order_number' => $transaction->order_number,
                'ad_order_no' => $adOrderNo,
            ]);
            throw new \Exception('No se pudo obtener el detalle de la orden desde la API de Binance');
        }

        // Obtener metadata actual o inicializar
        $metadata = $transaction->metadata ?? [];
        
        // Agregar información del detalle al metadata
        $metadata['order_detail'] = $orderDetail;
        
        // Extraer merchantNo del enrich
        $merchantNo = $orderDetail['merchantNo'] ?? null;
        
        // Extraer idNumber
        $idNumber = $orderDetail['idNumber'] ?? null;
        
        // Determinar si es compra o venta para obtener la información correcta
        $isBuy = ($tradeType === 'BUY');
        
        // Extraer información según el tipo de operación
        $counterPartyNickname = null; // Para counter_party (nickname)
        $counterPartyFullName = null; // Para full_name (buyerName/sellerName)
        
        if ($isBuy) {
            // Si es compra, el usuario es comprador, la contraparte es el vendedor (seller)
            $sellerName = $orderDetail['sellerName'] ?? null;
            $sellerNickname = $orderDetail['sellerNickname'] ?? null;
            $sellerMobilePhone = $orderDetail['sellerMobilePhone'] ?? null;
            
            // DEBUG: Log para verificar qué valores se están extrayendo
            Log::debug('Extracting seller info from enrich', [
                'sellerName' => $sellerName,
                'sellerNickname' => $sellerNickname,
                'orderDetail_keys' => array_keys($orderDetail),
            ]);
            
            // Usar el nickname del vendedor como counter_party
            $counterPartyNickname = $sellerNickname;
            // Usar el nombre completo del vendedor como full_name
            $counterPartyFullName = $sellerName;
            
            // Agregar información del vendedor (contraparte) al metadata
            $metadata['counter_party_info'] = [
                'name' => $sellerName,
                'nickname' => $sellerNickname,
                'mobile_phone' => $sellerMobilePhone,
            ];
            $metadata['seller_info'] = [
                'name' => $sellerName,
                'nickname' => $sellerNickname,
                'mobile_phone' => $sellerMobilePhone,
            ];
        } else {
            // Si es venta, el usuario es vendedor, la contraparte es el comprador (buyer)
            $buyerName = $orderDetail['buyerName'] ?? null;
            $buyerNickname = $orderDetail['buyerNickname'] ?? null;
            $buyerMobilePhone = $orderDetail['buyerMobilePhone'] ?? null;
            
            // DEBUG: Log para verificar qué valores se están extrayendo
            Log::debug('Extracting buyer info from enrich', [
                'buyerName' => $buyerName,
                'buyerNickname' => $buyerNickname,
                'orderDetail_keys' => array_keys($orderDetail),
            ]);
            
            // Usar el nickname del comprador como counter_party
            $counterPartyNickname = $buyerNickname;
            // Usar el nombre completo del comprador como full_name
            $counterPartyFullName = $buyerName;
            
            // Agregar información del comprador (contraparte) al metadata
            $metadata['counter_party_info'] = [
                'name' => $buyerName,
                'nickname' => $buyerNickname,
                'mobile_phone' => $buyerMobilePhone,
            ];
            $metadata['buyer_info'] = [
                'name' => $buyerName,
                'nickname' => $buyerNickname,
                'mobile_phone' => $buyerMobilePhone,
            ];
        }
        
        // Buscar o crear CounterParty usando el nickname
        $counterParty = null;
        $exchange = $transaction->exchange ?? 'binance';
        
        if ($counterPartyNickname && $this->userId) {
            try {
                $counterParty = CounterParty::findOrCreateForTransaction(
                    $this->userId,
                    $exchange,
                    $counterPartyNickname,
                    $merchantNo,
                    $counterPartyFullName
                );
                
                Log::info('CounterParty found or created', [
                    'counter_party_id' => $counterParty->id,
                    'counter_party' => $counterParty->counter_party,
                    'full_name' => $counterParty->full_name,
                    'merchant_no' => $counterParty->merchant_no,
                    'was_recently_created' => $counterParty->wasRecentlyCreated ?? false,
                ]);
                
                // Si tenemos información del enrich (idNumber), actualizar el CounterParty
                if ($idNumber && !$counterParty->counter_party_dni) {
                    $counterParty->counter_party_dni = $idNumber;
                    $counterParty->save();
                    Log::info('CounterParty updated with idNumber', [
                        'counter_party_id' => $counterParty->id,
                        'has_id_number' => !empty($idNumber),
                    ]);
                }
                
                // Si el CounterParty ya tiene dni_type y counter_party_dni, usar esos valores
                if ($counterParty->dni_type && $counterParty->counter_party_dni) {
                    $idNumber = $counterParty->counter_party_dni;
                }
            } catch (\Exception $e) {
                Log::error('Error creating or finding CounterParty', [
                    'user_id' => $this->userId,
                    'exchange' => $exchange,
                    'counter_party_nickname' => $counterPartyNickname,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continuar con el proceso aunque falle la creación del CounterParty
            }
        } else {
            Log::warning('Cannot create CounterParty: missing required data', [
                'has_counter_party_nickname' => !empty($counterPartyNickname),
                'has_user_id' => !empty($this->userId),
                'counter_party_nickname' => $counterPartyNickname,
            ]);
        }
        
        // Preparar datos para actualizar la transacción
        $updateData = [
            'metadata' => $metadata,
        ];
        
        // SIEMPRE actualizar counter_party con el nickname (incluso si es null)
        // Esto asegura que se actualice con el valor correcto del enrich
        $updateData['counter_party'] = $counterPartyNickname;
        
        // SIEMPRE actualizar counter_party_full_name con el nombre completo (incluso si es null)
        $updateData['counter_party_full_name'] = $counterPartyFullName;
        
        // Actualizar counter_party_dni si tenemos información
        if ($idNumber) {
            $updateData['counter_party_dni'] = $idNumber;
        }
        
        // Si tenemos dni_type del CounterParty, usarlo
        if ($counterParty && $counterParty->dni_type) {
            $updateData['dni_type'] = $counterParty->dni_type;
        }
        
        // DEBUG: Log antes de actualizar
        Log::debug('About to update transaction with enrich data', [
            'transaction_id' => $transaction->id,
            'order_number' => $transaction->order_number,
            'update_data' => $updateData,
            'counter_party_nickname' => $counterPartyNickname,
            'counter_party_full_name' => $counterPartyFullName,
        ]);
        
        // Verificar que tenemos datos para actualizar antes de hacer el update
        if (empty($updateData['counter_party']) && empty($updateData['counter_party_full_name'])) {
            Log::warning('EnrichP2POrderWithDetail: No counter party data extracted', [
                'transaction_id' => $transaction->id,
                'order_number' => $transaction->order_number,
                'ad_order_no' => $adOrderNo,
                'trade_type' => $tradeType,
                'is_buy' => $isBuy,
                'order_detail_keys' => array_keys($orderDetail),
            ]);
        }
        
        // Actualizar la transacción
        $transaction->update($updateData);
        
        // DEBUG: Log después de actualizar para verificar
        $transaction->refresh();
        Log::debug('Transaction updated, verifying values', [
            'transaction_id' => $transaction->id,
            'counter_party' => $transaction->counter_party,
            'counter_party_full_name' => $transaction->counter_party_full_name,
            'counter_party_dni' => $transaction->counter_party_dni ? '***' : null,
            'dni_type' => $transaction->dni_type,
            'counter_party_created' => $counterParty ? $counterParty->id : null,
        ]);
        
        Log::info('P2P order enriched with detail', [
            'order_number' => $transaction->order_number,
            'ad_order_no' => $adOrderNo,
            'trade_type' => $tradeType,
            'is_buy' => $isBuy,
            'counter_party_nickname' => $counterPartyNickname,
            'counter_party_full_name' => $counterPartyFullName,
            'has_id_number' => !empty($idNumber),
            'id_number' => $idNumber ? '***' : null, // No loguear el DNI completo por seguridad
            'has_counter_party_info' => isset($metadata['counter_party_info']),
            'counter_party_updated' => isset($updateData['counter_party']),
            'counter_party_full_name_updated' => isset($updateData['counter_party_full_name']),
            'counter_party_dni_updated' => isset($updateData['counter_party_dni']),
            'dni_type_updated' => isset($updateData['dni_type']),
            'update_data_keys' => array_keys($updateData),
            'final_counter_party' => $transaction->counter_party,
            'final_counter_party_full_name' => $transaction->counter_party_full_name,
        ]);
    }

    /**
     * Guardar respuesta del endpoint P2P en un archivo de log específico
     */
    private function logP2PResponseToFile(
        int $page, 
        array $requestParams, 
        string $responseBody, 
        ?array $responseData = null, 
        bool $success = true
    ): void {
        try {
            $logDir = storage_path('logs/binance_p2p');
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/p2p_order_history_' . date('Y-m-d') . '.log';
            
            // Extraer tradeType del response si está disponible en las órdenes
            $tradeTypes = [];
            if ($responseData && isset($responseData['data']) && is_array($responseData['data'])) {
                foreach ($responseData['data'] as $order) {
                    if (isset($order['tradeType'])) {
                        $tradeTypes[] = $order['tradeType'];
                    }
                }
                $tradeTypes = array_unique($tradeTypes);
            }
            
            $logEntry = [
                'timestamp' => now()->toIso8601String(),
                'status' => $success ? 'SUCCESS' : 'ERROR',
                'trade_types_in_response' => !empty($tradeTypes) ? $tradeTypes : null,
                'page' => $page,
                'request' => [
                    'endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory',
                    'params' => $requestParams
                ],
                'response' => [
                    'raw_body' => $responseBody,
                    'parsed_data' => $responseData,
                    'orders_count' => $responseData && isset($responseData['data']) && is_array($responseData['data']) 
                        ? count($responseData['data']) 
                        : 0,
                    'total' => $responseData['total'] ?? null
                ]
            ];
            
            $logLine = json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n" . str_repeat('-', 100) . "\n\n";
            
            file_put_contents($logFile, $logLine, FILE_APPEND);
        } catch (\Exception $e) {
            // Si falla el logging a archivo, al menos loguear el error
            Log::warning('Failed to write P2P response to log file', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Guardar respuesta del endpoint de detalle de orden P2P en un archivo de log específico
     */
    private function logP2POrderDetailResponseToFile(
        string $adOrderNo,
        array $requestBody,
        string $responseBody,
        ?array $responseData = null,
        int $responseStatus = 200
    ): void {
        try {
            $logDir = storage_path('logs/binance_p2p');
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/p2p_order_detail_' . date('Y-m-d') . '.log';
            
            // Extraer información relevante del detalle
            $orderDetail = $responseData['data'] ?? null;
            $availableFields = $orderDetail && is_array($orderDetail) ? array_keys($orderDetail) : null;
            
            $logEntry = [
                'timestamp' => now()->toIso8601String(),
                'status' => $responseStatus >= 200 && $responseStatus < 300 ? 'SUCCESS' : 'ERROR',
                'adOrderNo' => $adOrderNo,
                'request' => [
                    'endpoint' => '/sapi/v1/c2c/orderMatch/getUserOrderDetail',
                    'body' => $requestBody
                ],
                'response' => [
                    'status_code' => $responseStatus,
                    'raw_body' => $responseBody,
                    'parsed_data' => $responseData,
                    'success' => $responseData['success'] ?? null,
                    'code' => $responseData['code'] ?? null,
                    'message' => $responseData['message'] ?? null,
                    'has_data' => isset($responseData['data']),
                    'available_fields' => $availableFields,
                    'order_detail' => $orderDetail,
                    // Información específica si está disponible
                    'tradeType' => $orderDetail['tradeType'] ?? null,
                    'orderStatus' => $orderDetail['orderStatus'] ?? null,
                    'orderNumber' => $orderDetail['orderNumber'] ?? null,
                    'idNumber' => $orderDetail['idNumber'] ?? null,
                    'buyerName' => $orderDetail['buyerName'] ?? null,
                    'buyerNickname' => $orderDetail['buyerNickname'] ?? null,
                    'buyerMobilePhone' => $orderDetail['buyerMobilePhone'] ?? null,
                    'sellerName' => $orderDetail['sellerName'] ?? null,
                    'sellerNickname' => $orderDetail['sellerNickname'] ?? null,
                    'sellerMobilePhone' => $orderDetail['sellerMobilePhone'] ?? null,
                ]
            ];
            
            $logLine = json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n" . str_repeat('=', 100) . "\n\n";
            
            file_put_contents($logFile, $logLine, FILE_APPEND);
        } catch (\Exception $e) {
            // Si falla el logging a archivo, al menos loguear el error
            Log::warning('Failed to write P2P order detail response to log file', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getDepositHistory(Carbon $startTime, Carbon $endTime): array
    {
        $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/capital/deposit/hisrec', [
            'startTime' => $startTime->timestamp * 1000,
            'endTime' => $endTime->timestamp * 1000,
            'limit' => 1000
        ]);

        $data = $response->json();
        
        // La API puede devolver un array directamente o dentro de una clave
        if (is_array($data)) {
            // Si tiene clave 'rows', usar esa
            if (isset($data['rows']) && is_array($data['rows'])) {
                return $data['rows'];
            }
            // Si es un array numérico, devolverlo directamente
            if (array_values($data) === $data) {
                return $data;
            }
        }
        
        return [];
    }

    private function getWithdrawalHistory(Carbon $startTime, Carbon $endTime): array
    {
        $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/capital/withdraw/history', [
            'startTime' => $startTime->timestamp * 1000,
            'endTime' => $endTime->timestamp * 1000,
            'limit' => 1000
        ]);

        $data = $response->json();
        
        // La API puede devolver un array directamente o dentro de una clave
        if (is_array($data)) {
            // Si tiene clave 'rows', usar esa
            if (isset($data['rows']) && is_array($data['rows'])) {
                return $data['rows'];
            }
            // Si es un array numérico, devolverlo directamente
            if (array_values($data) === $data) {
                return $data;
            }
        }
        
        return [];
    }

    private function getPayTransactions(Carbon $startTime, Carbon $endTime): array
    {
        $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/pay/transactions', [
            'startTime' => $startTime->timestamp * 1000,
            'endTime' => $endTime->timestamp * 1000,
            'limit' => 1000
        ]);

        return $response->json()['data'] ?? [];
    }

    private function getInternalTransfers(Carbon $startTime, Carbon $endTime): array
    {
        $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/asset/transfer', [
            'startTime' => $startTime->timestamp * 1000,
            'endTime' => $endTime->timestamp * 1000,
            'limit' => 1000
        ]);

        $data = $response->json();
        
        // La API puede devolver {'rows': [...]} o directamente un array
        if (is_array($data)) {
            if (isset($data['rows']) && is_array($data['rows'])) {
                return $data['rows'];
            }
            // Si es un array numérico, devolverlo directamente
            if (array_values($data) === $data) {
                return $data;
            }
        }
        
        return [];
    }

    /**
     * Obtener símbolos relevantes para sincronizar
     * Primero intenta obtener símbolos de transacciones existentes del usuario
     * Si no hay, usa los símbolos más comunes (BTC, ETH, BNB)
     */
    private function getRelevantTradingPairs(Carbon $startTime, Carbon $endTime): array
    {
        // Si hay userId, intentar obtener símbolos de transacciones existentes
        if ($this->userId) {
            $existingTransactions = Transaction::where('user_id', $this->userId)
                ->where('transaction_type', 'spot_trade')
                ->whereBetween('binance_create_time', [$startTime, $endTime])
                ->select('asset_type', 'fiat_type')
                ->distinct()
                ->get();
            
            if ($existingTransactions->isNotEmpty()) {
                $existingSymbols = $existingTransactions
                    ->map(function ($tx) {
                        // Reconstruir símbolo (ej: BTC + USDT = BTCUSDT)
                        return ($tx->asset_type ?? '') . ($tx->fiat_type ?? '');
                    })
                    ->filter() // Eliminar strings vacíos
                    ->unique()
                    ->values()
                    ->toArray();
                
                if (!empty($existingSymbols)) {
                    Log::info('Using existing transaction symbols for sync', [
                        'symbols_count' => count($existingSymbols),
                        'symbols' => array_slice($existingSymbols, 0, 20) // Log primeros 20
                    ]);
                    return $existingSymbols;
                }
            }
        }
        
        // Si no hay símbolos existentes, usar los más comunes
        // Esto evita hacer cientos de peticiones innecesarias
        $commonSymbols = [
            'BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'ADAUSDT', 'SOLUSDT',
            'XRPUSDT', 'DOGEUSDT', 'DOTUSDT', 'LINKUSDT', 'LTCUSDT',
            'MATICUSDT', 'AVAXUSDT', 'UNIUSDT', 'ATOMUSDT', 'ETCUSDT'
        ];
        
        Log::info('Using common trading pairs for sync', [
            'symbols_count' => count($commonSymbols)
        ]);
        
        return $commonSymbols;
    }

    /**
     * Dividir un rango de tiempo en chunks de máximo N horas
     * Esto es necesario porque la API de Binance solo permite consultar máximo 24 horas
     */
    private function splitTimeRange(Carbon $startTime, Carbon $endTime, int $maxHours = 23): array
    {
        $chunks = [];
        $currentStart = $startTime->copy();
        
        while ($currentStart->lt($endTime)) {
            $currentEnd = $currentStart->copy()->addHours($maxHours);
            
            // Asegurar que no exceda el endTime
            if ($currentEnd->gt($endTime)) {
                $currentEnd = $endTime->copy();
            }
            
            $chunks[] = [
                'start' => $currentStart->copy(),
                'end' => $currentEnd->copy()
            ];
            
            // Mover al siguiente chunk (1 segundo después del final para evitar solapamiento)
            $currentStart = $currentEnd->copy()->addSecond();
        }
        
        return $chunks;
    }

    private function getActiveTradingPairs(): array
    {
        $cacheKey = 'binance_active_trading_pairs';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v3/exchangeInfo');
            
            if ($response->successful()) {
                $data = $response->json();
                return array_column($data['symbols'], 'symbol');
            }
            
            return ['BTCUSDT', 'ETHUSDT', 'BNBUSDT']; // Fallback
        });
    }

    /**
     * Método específico para peticiones P2P que usa el mismo formato que makeAuthenticatedRequest
     */
    private function makeP2PRequest(string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $timestamp = time() * 1000;
        
        // Preparar parámetros: asegurar que todos los valores sean strings para la query string
        $cleanParams = [];
        foreach ($params as $key => $value) {
            // IMPORTANTE: No filtrar valores numéricos válidos (incluyendo 0)
            // Solo filtrar null, false, y strings vacíos
            if ($value !== null && $value !== false && $value !== '') {
                // Convertir todos los valores a string para la query string
                // Esto incluye números enteros y decimales
                $cleanParams[$key] = (string) $value;
            }
        }
        
        // Agregar timestamp (Binance requiere que timestamp esté incluido en la firma)
        $cleanParams['timestamp'] = (string) $timestamp;
        
        // Ordenar los parámetros alfabéticamente para la firma (requerido por Binance)
        ksort($cleanParams);
        
        // Construir query string usando http_build_query
        // Usar el separador '&' explícitamente y no codificar arrays
        $queryString = http_build_query($cleanParams, '', '&', PHP_QUERY_RFC3986);
        
        // Generar firma usando la query string (sin el signature)
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);
        
        // Construir URL final con signature
        $url = $this->baseUrl . $endpoint . '?' . $queryString . '&signature=' . urlencode($signature);

        return Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
        ])->timeout(90)->get($url);
    }

    /**
     * Método específico para peticiones POST P2P autenticadas
     * Para endpoints POST de Binance SAPI, los parámetros de autenticación van en la query string
     * y el body va como JSON
     */
    private function makeP2PPostRequest(string $endpoint, array $bodyParams = []): \Illuminate\Http\Client\Response
    {
        $timestamp = time() * 1000;
        
        // Para POST, los parámetros de autenticación (timestamp) van en la query string
        $queryParams = [
            'timestamp' => (string) $timestamp,
        ];
        
        // Ordenar los parámetros alfabéticamente para la firma (requerido por Binance)
        ksort($queryParams);
        
        // Construir query string para la firma
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        
        // Generar firma usando la query string
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);
        
        // Construir URL final con timestamp y signature en la query string
        $url = $this->baseUrl . $endpoint . '?' . $queryString . '&signature=' . urlencode($signature);

        return Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(90)->post($url, $bodyParams);
    }

    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $timestamp = time() * 1000;
        
        // Preparar parámetros: asegurar que todos los valores sean strings para la query string
        $cleanParams = [];
        foreach ($params as $key => $value) {
            // IMPORTANTE: No filtrar valores numéricos válidos (incluyendo 0)
            // Solo filtrar null, false, y strings vacíos
            if ($value !== null && $value !== false && $value !== '') {
                // Convertir todos los valores a string para la query string
                // Esto incluye números enteros y decimales
                $cleanParams[$key] = (string) $value;
            }
        }
        
        // Agregar timestamp (Binance requiere que timestamp esté incluido en la firma)
        $cleanParams['timestamp'] = (string) $timestamp;
        
        // Ordenar los parámetros alfabéticamente para la firma (requerido por Binance)
        ksort($cleanParams);
        
        // Construir query string usando http_build_query
        // Usar el separador '&' explícitamente y no codificar arrays
        $queryString = http_build_query($cleanParams, '', '&', PHP_QUERY_RFC3986);
        
        // Generar firma usando la query string (sin el signature)
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);
        
        // Construir URL final con signature
        $url = $this->baseUrl . $endpoint . '?' . $queryString . '&signature=' . urlencode($signature);

        return Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
        ])->timeout(90)->$method($url);
    }

    // Métodos de mapeo de estados
    private function mapP2PStatus(string $status): string
    {
        return match($status) {
            'PENDING' => 'pending',
            'PROCESSING' => 'processing',
            'COMPLETED' => 'completed',
            'CANCELLED' => 'cancelled',
            'FAILED' => 'failed',
            'EXPIRED' => 'expired',
            default => 'pending'
        };
    }

    private function mapDepositStatus(int $status): string
    {
        return match($status) {
            0 => 'pending',
            1 => 'completed',
            default => 'pending'
        };
    }

    private function mapWithdrawalStatus(int $status): string
    {
        return match($status) {
            0 => 'pending',
            1 => 'processing',
            2 => 'completed',
            3 => 'cancelled',
            4 => 'failed',
            5 => 'processing', // Awaiting approval
            6 => 'completed',   // Completed (según documentación Binance)
            default => 'pending'
        };
    }

    private function mapPayStatus(string $status): string
    {
        return match($status) {
            'PENDING' => 'pending',
            'SUCCESS' => 'completed',
            'FAILED' => 'failed',
            'CANCELLED' => 'cancelled',
            default => 'pending'
        };
    }

    private function mapTransferStatus(string $status): string
    {
        return match($status) {
            'PENDING' => 'pending',
            'CONFIRMED' => 'completed',
            'FAILED' => 'failed',
            default => 'pending'
        };
    }

    private function extractBaseAsset(string $symbol): string
    {
        // Extraer el activo base del símbolo (ej: BTC de BTCUSDT)
        $patterns = ['USDT', 'USDC', 'BUSD', 'BNB', 'ETH', 'BTC'];
        foreach ($patterns as $pattern) {
            if (str_ends_with($symbol, $pattern)) {
                return str_replace($pattern, '', $symbol);
            }
        }
        return $symbol;
    }

    private function extractQuoteAsset(string $symbol): string
    {
        // Extraer el activo quote del símbolo (ej: USDT de BTCUSDT)
        $patterns = ['USDT', 'USDC', 'BUSD', 'BNB', 'ETH', 'BTC'];
        foreach ($patterns as $pattern) {
            if (str_ends_with($symbol, $pattern)) {
                return $pattern;
            }
        }
        return 'USDT'; // Fallback
    }
}
