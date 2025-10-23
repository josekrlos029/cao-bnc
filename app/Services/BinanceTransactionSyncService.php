<?php

namespace App\Services;

use App\Models\BinanceCredential;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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

    public function __construct(BinanceCredential $credentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->isTestnet = $credentials->is_testnet;
            $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
            $this->sapiBaseUrl = $this->isTestnet ? 'https://testnet.binance.vision/sapi/v1' : self::SAPI_BASE_URL;
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
            $results['spot_trades'] = $this->syncSpotTrades($startTime, $endTime);
            
            // 2. Sincronizar órdenes P2P
            $results['p2p_orders'] = $this->syncP2POrders($startTime, $endTime);
            
            // 3. Sincronizar depósitos
            $results['deposits'] = $this->syncDeposits($startTime, $endTime);
            
            // 4. Sincronizar retiros
            $results['withdrawals'] = $this->syncWithdrawals($startTime, $endTime);
            
            // 5. Sincronizar transacciones de Binance Pay
            $results['pay_transactions'] = $this->syncPayTransactions($startTime, $endTime);
            
            // 6. Sincronizar transferencias internas
            $results['internal_transfers'] = $this->syncInternalTransfers($startTime, $endTime);
            
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
     */
    public function syncSpotTrades(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(7);
        $endTime = $endTime ?? now();

        try {
            $symbols = $this->getActiveTradingPairs();
            
            foreach ($symbols as $symbol) {
                $trades = $this->getSpotTrades($symbol, $startTime, $endTime);
                
                foreach ($trades as $trade) {
                    $transaction = Transaction::updateOrCreate(
                        [
                            'trade_id' => $trade['id'],
                            'transaction_type' => 'spot_trade'
                        ],
                        [
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
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing spot trades', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
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
                $transaction = Transaction::updateOrCreate(
                    [
                        'order_number' => $order['orderNumber'],
                        'transaction_type' => 'p2p_order'
                    ],
                    [
                        'advertisement_order_number' => $order['advertisementOrderNumber'] ?? null,
                        'asset_type' => $order['asset'],
                        'fiat_type' => $order['fiat'],
                        'order_type' => $order['orderType'],
                        'quantity' => $order['unitPrice'],
                        'price' => $order['unitPrice'],
                        'total_price' => $order['totalPrice'],
                        'commission' => $order['commission'] ?? 0,
                        'payment_method' => $order['paymentMethod'] ?? null,
                        'counter_party' => $order['counterpartNickName'] ?? null,
                        'status' => $this->mapP2PStatus($order['orderStatus']),
                        'binance_create_time' => Carbon::createFromTimestamp($order['createTime'] / 1000),
                        'binance_update_time' => Carbon::createFromTimestamp($order['updateTime'] / 1000),
                        'source_endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory',
                        'metadata' => $order,
                        'last_synced_at' => now(),
                    ]
                );
                
                if ($transaction->wasRecentlyCreated) {
                    $synced++;
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
                $transaction = Transaction::updateOrCreate(
                    [
                        'transaction_id' => $deposit['txId'],
                        'transaction_type' => 'deposit'
                    ],
                    [
                        'asset_type' => $deposit['coin'],
                        'quantity' => $deposit['amount'],
                        'status' => $this->mapDepositStatus($deposit['status']),
                        'network_fee' => $deposit['networkFee'] ?? 0,
                        'binance_create_time' => Carbon::createFromTimestamp($deposit['insertTime'] / 1000),
                        'source_endpoint' => '/sapi/v1/capital/deposit/hisrec',
                        'metadata' => $deposit,
                        'last_synced_at' => now(),
                    ]
                );
                
                if ($transaction->wasRecentlyCreated) {
                    $synced++;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing deposits', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
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
                $transaction = Transaction::updateOrCreate(
                    [
                        'transaction_id' => $withdrawal['id'],
                        'transaction_type' => 'withdrawal'
                    ],
                    [
                        'asset_type' => $withdrawal['coin'],
                        'quantity' => $withdrawal['amount'],
                        'status' => $this->mapWithdrawalStatus($withdrawal['status']),
                        'network_fee' => $withdrawal['transactionFee'] ?? 0,
                        'binance_create_time' => Carbon::createFromTimestamp($withdrawal['applyTime'] / 1000),
                        'source_endpoint' => '/sapi/v1/capital/withdraw/history',
                        'metadata' => $withdrawal,
                        'last_synced_at' => now(),
                    ]
                );
                
                if ($transaction->wasRecentlyCreated) {
                    $synced++;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing withdrawals', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
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
                $transaction = Transaction::updateOrCreate(
                    [
                        'transaction_id' => $payTx['transactionId'],
                        'transaction_type' => 'pay_transaction'
                    ],
                    [
                        'asset_type' => $payTx['currency'],
                        'quantity' => $payTx['amount'],
                        'status' => $this->mapPayStatus($payTx['status']),
                        'binance_create_time' => Carbon::createFromTimestamp($payTx['createTime'] / 1000),
                        'source_endpoint' => '/sapi/v1/pay/transactions',
                        'metadata' => $payTx,
                        'last_synced_at' => now(),
                    ]
                );
                
                if ($transaction->wasRecentlyCreated) {
                    $synced++;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing pay transactions', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
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
            
            foreach ($transfers as $transfer) {
                $transaction = Transaction::updateOrCreate(
                    [
                        'transaction_id' => $transfer['tranId'],
                        'transaction_type' => 'internal_transfer'
                    ],
                    [
                        'asset_type' => $transfer['asset'],
                        'quantity' => $transfer['amount'],
                        'status' => $this->mapTransferStatus($transfer['status']),
                        'binance_create_time' => Carbon::createFromTimestamp($transfer['timestamp'] / 1000),
                        'source_endpoint' => '/sapi/v1/asset/transfer',
                        'metadata' => $transfer,
                        'last_synced_at' => now(),
                    ]
                );
                
                if ($transaction->wasRecentlyCreated) {
                    $synced++;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing internal transfers', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            return 0;
        }
    }

    // Métodos privados para hacer requests a la API

    private function getSpotTrades(string $symbol, Carbon $startTime, Carbon $endTime): array
    {
        try {
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

            Log::warning('Binance API returned unsuccessful response for spot trades', [
                'symbol' => $symbol,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

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
        try {
            $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/c2c/orderMatch/listUserOrderHistory', [
                'startTimestamp' => $startTime->timestamp * 1000,
                'endTimestamp' => $endTime->timestamp * 1000,
                'limit' => 1000
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
            }

            Log::warning('Binance API returned unsuccessful response for P2P orders', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::warning('Error fetching P2P orders from Binance API', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function getDepositHistory(Carbon $startTime, Carbon $endTime): array
    {
        $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/capital/deposit/hisrec', [
            'startTime' => $startTime->timestamp * 1000,
            'endTime' => $endTime->timestamp * 1000,
            'limit' => 1000
        ]);

        return $response->json() ?? [];
    }

    private function getWithdrawalHistory(Carbon $startTime, Carbon $endTime): array
    {
        $response = $this->makeAuthenticatedRequest('GET', '/sapi/v1/capital/withdraw/history', [
            'startTime' => $startTime->timestamp * 1000,
            'endTime' => $endTime->timestamp * 1000,
            'limit' => 1000
        ]);

        return $response->json() ?? [];
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

        return $response->json() ?? [];
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

    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $timestamp = time() * 1000;
        $queryString = http_build_query(array_merge($params, ['timestamp' => $timestamp]));
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);

        $url = $this->baseUrl . $endpoint . '?' . $queryString . '&signature=' . $signature;

        return Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
        ])->timeout(30)->$method($url);
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
