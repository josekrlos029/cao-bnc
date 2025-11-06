<?php

namespace App\Services;

use App\Models\OKXCredential;
use App\Models\Transaction;
use App\Services\OKXService;
use App\Jobs\EnrichTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class OKXTransactionSyncService
{
    private const BASE_URL = 'https://www.okx.com';
    
    private string $apiKey;
    private string $secretKey;
    private string $passphrase;
    private bool $isTestnet;
    private string $baseUrl;
    private ?int $userId;

    public function __construct(OKXCredential $credentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->passphrase = $credentials->passphrase;
            $this->isTestnet = $credentials->is_testnet;
            $this->baseUrl = self::BASE_URL;
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
            // Sincronizar órdenes P2P
            $results['p2p_orders'] = $this->syncP2POrders($startTime, $endTime);
            
            Log::info('OKX Transaction Sync Completed', [
                'results' => $results,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('OKX Transaction Sync Failed', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            
            throw $e;
        }
    }

    /**
     * Sincronizar órdenes P2P de OKX
     * Endpoint: GET /api/v5/p2p/order/list
     * Documentación: https://www.okx.com/docs-v5/en/#rest-api-p2p-get-order-list
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
                    // Registrar toda la respuesta en log antes de procesar
                    $this->logOrderResponse($order);
                    
                    // Extraer campos principales según la estructura real del response
                    $orderId = $order['orderId'] ?? null;
                    $adId = $order['adId'] ?? null;
                    $side = $order['side'] ?? null; // 'buy' o 'sell'
                    $orderStatus = $order['orderStatus'] ?? null; // 'completed', 'cancelled', etc.
                    
                    if (!$orderId) {
                        Log::warning('OKX P2P order missing orderId', ['order' => $order]);
                        continue;
                    }
                    
                    // Mapear el tipo de orden (BUY/SELL)
                    $tradeType = null;
                    if ($side === 'buy' || $side === 'BUY') {
                        $tradeType = 'BUY';
                    } elseif ($side === 'sell' || $side === 'SELL') {
                        $tradeType = 'SELL';
                    }

                    // Mapear estado
                    $status = $this->mapP2PStatus($orderStatus);

                    // Extraer información de cantidades y precios
                    // cryptoAmount: cantidad de crypto (ej: "5000.00")
                    // cryptoCurrency: tipo de crypto (ej: "usdt")
                    // fiatAmount: cantidad en fiat (ej: "18950000.00")
                    // fiatCurrency: tipo de fiat (ej: "cop")
                    // unitPrice o exchangeRate: precio unitario
                    $quantity = isset($order['cryptoAmount']) ? (float)$order['cryptoAmount'] : 0;
                    $price = isset($order['unitPrice']) ? (float)$order['unitPrice'] : (isset($order['exchangeRate']) ? (float)$order['exchangeRate'] : 0);
                    $amount = isset($order['fiatAmount']) ? (float)$order['fiatAmount'] : 0;
                    
                    // Si no hay amount pero hay quantity y price, calcularlo
                    if ($amount == 0 && $quantity > 0 && $price > 0) {
                        $amount = $quantity * $price;
                    }

                    // Extraer información de monedas
                    $assetType = $order['cryptoCurrency'] ?? null;
                    $fiatType = $order['fiatCurrency'] ?? null;

                    // Extraer información de la contraparte
                    $counterPartyNickName = null;
                    $counterPartyFullName = null;
                    if (isset($order['counterpartyDetail']) && is_array($order['counterpartyDetail'])) {
                        $counterPartyNickName = $order['counterpartyDetail']['nickName'] ?? null;
                        $counterPartyFullName = $order['counterpartyDetail']['realName'] ?? null;
                    }

                    // Extraer método de pago
                    $paymentMethod = $order['makerPaymentMethod'] ?? null;

                    // Extraer timestamps - OKX usa milisegundos
                    $createTime = now();
                    if (isset($order['createdTimestamp'])) {
                        $timestamp = (string)$order['createdTimestamp'];
                        if (strlen($timestamp) === 13) {
                            $createTime = Carbon::createFromTimestamp((int)$timestamp / 1000);
                        } else {
                            $createTime = Carbon::createFromTimestamp((int)$timestamp);
                        }
                    }
                    
                    $updateTime = $createTime;
                    if (isset($order['updatedTimestamp'])) {
                        $timestamp = (string)$order['updatedTimestamp'];
                        if (strlen($timestamp) === 13) {
                            $updateTime = Carbon::createFromTimestamp((int)$timestamp / 1000);
                        } else {
                            $updateTime = Carbon::createFromTimestamp((int)$timestamp);
                        }
                    }

                    $transaction = Transaction::updateOrCreate(
                        [
                            'order_number' => (string) $orderId,
                            'transaction_type' => 'p2p_order',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'okx',
                            'advertisement_order_number' => $adId ? (string)$adId : null,
                            'asset_type' => $assetType,
                            'fiat_type' => $fiatType,
                            'order_type' => $tradeType ?? 'UNKNOWN',
                            'quantity' => $quantity,
                            'price' => $price,
                            'amount' => $amount,
                            'total_price' => $amount,
                            'payment_method' => $paymentMethod,
                            'counter_party' => $counterPartyNickName,
                            'counter_party_full_name' => $counterPartyFullName,
                            'status' => $status,
                            'binance_create_time' => $createTime,
                            'binance_update_time' => $updateTime,
                            'source_endpoint' => '/api/v5/p2p/order/list',
                            'metadata' => $order, // Guardar toda la respuesta para análisis posterior
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    // Despachar job para enriquecimiento en background
                    if ($orderId) {
                        try {
                            // Marcar transacción como pending para enriquecimiento
                            if (Schema::hasColumn('transactions', 'enrichment_status')) {
                                $transaction->update(['enrichment_status' => 'pending']);
                            }
                            
                            EnrichTransaction::dispatch($transaction->id);
                            Log::debug('EnrichTransaction job dispatched for OKX transaction', [
                                'transaction_id' => $transaction->id,
                                'order_number' => $orderId
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Error dispatching EnrichTransaction job', [
                                'transaction_id' => $transaction->id,
                                'order_number' => $orderId,
                                'error' => $e->getMessage()
                            ]);
                            // Continuar aunque falle el despacho del job
                        }
                    }
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                    
                    // Rate limiting: pequeño delay entre órdenes
                    usleep(100000); // 100ms
                } catch (\Exception $e) {
                    Log::error('Error processing individual OKX P2P order', [
                        'order' => $order,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing OKX P2P orders', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            return 0;
        }
    }

    /**
     * Obtener historial de órdenes P2P de OKX
     * Endpoint: GET /api/v5/p2p/order/list
     * Documentación: https://www.okx.com/docs-v5/en/#rest-api-p2p-get-order-list
     */
    private function getP2POrderHistory(Carbon $startTime, Carbon $endTime): array
    {
        $allOrders = [];
        
        try {
            $hasMore = true;
            $pageIndex = 1;
            $pageSize = 20; // Filas por página
            
            while ($hasMore) {
                // Construir parámetros según documentación
                $requestParams = [
                    'pageIndex' => $pageIndex,
                    'pageSize' => $pageSize,
                    'start' => (string)($startTime->timestamp * 1000), // Timestamp en milisegundos
                    'end' => (string)($endTime->timestamp * 1000), // Timestamp en milisegundos
                ];
                
                // Log detallado del request antes de enviarlo
                Log::debug('OKX P2P Order Request', [
                    'endpoint' => '/api/v5/p2p/order/list',
                    'params' => $requestParams,
                ]);
                
                $response = $this->makeAuthenticatedRequest('GET', '/api/v5/p2p/order/list', $requestParams);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Registrar respuesta completa en log
                    $this->logOrderListResponse($data, $pageIndex);
                    
                    // Verificar code (OKX usa 'code' con valor 0 o '0' para éxito)
                    $code = $data['code'] ?? null;
                    
                    // OKX puede devolver code como string '0' o integer 0
                    if ($code === 0 || $code === '0') {
                        // Extraer órdenes de la respuesta
                        // Estructura: { code: 0, data: [array de órdenes], msg: "" }
                        $orders = [];
                        if (isset($data['data']) && is_array($data['data'])) {
                            // data es directamente un array de órdenes
                            $orders = $data['data'];
                        }
                        
                        $allOrders = array_merge($allOrders, $orders);
                        
                        // Verificar si hay más páginas
                        // Si hay menos órdenes que pageSize, probablemente no hay más páginas
                        $currentCount = count($allOrders);
                        $ordersOnThisPage = count($orders);
                        
                        if ($ordersOnThisPage >= $pageSize && !empty($orders)) {
                            // Hay más páginas, continuar
                            $pageIndex++;
                            
                            // Rate limiting: delay entre páginas
                            usleep(200000); // 200ms
                        } else {
                            // No hay más páginas
                            $hasMore = false;
                        }
                    } else {
                        $msg = $data['msg'] ?? $data['message'] ?? 'Unknown error';
                        Log::warning('OKX API returned error response for P2P orders', [
                            'code' => $code,
                            'msg' => $msg,
                            'page' => $pageIndex
                        ]);
                        $hasMore = false;
                    }
                } else {
                    Log::warning('OKX API returned unsuccessful response for P2P orders', [
                        'endpoint' => '/api/v5/p2p/order/list',
                        'page' => $pageIndex,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    $hasMore = false;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error fetching P2P orders from OKX API', [
                'endpoint' => '/api/v5/p2p/order/list',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return $allOrders;
    }

    /**
     * Registrar respuesta completa de lista de órdenes en archivo de log
     */
    private function logOrderListResponse(array $response, int $pageIndex): void
    {
        try {
            $logDir = storage_path('logs/okx_p2p');
            
            // Crear directorio si no existe
            if (!File::exists($logDir)) {
                File::makeDirectory($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/p2p_order_list_' . now()->format('Y-m-d') . '.log';
            
            $logEntry = [
                'timestamp' => now()->toIso8601String(),
                'page_index' => $pageIndex,
                'response' => $response,
            ];
            
            File::append($logFile, json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n---\n");
            
            Log::info('OKX P2P Order List Response logged', [
                'log_file' => $logFile,
                'page_index' => $pageIndex
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging OKX P2P order list response', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Registrar respuesta de orden individual en log
     */
    private function logOrderResponse(array $order): void
    {
        try {
            $logDir = storage_path('logs/okx_p2p');
            
            // Crear directorio si no existe
            if (!File::exists($logDir)) {
                File::makeDirectory($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/p2p_order_detail_' . now()->format('Y-m-d') . '.log';
            
            $logEntry = [
                'timestamp' => now()->toIso8601String(),
                'order' => $order,
            ];
            
            File::append($logFile, json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n---\n");
            
            Log::debug('OKX P2P Order Response logged', [
                'log_file' => $logFile,
                'order_id' => $order['orderId'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging OKX P2P order response', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener timestamp en formato ISO 8601 con milisegundos para OKX
     * Formato requerido: 2020-12-08T09:08:57.715Z
     */
    private function getOKXTimestamp(): string
    {
        // Usar microtime para obtener precisión de milisegundos
        list($microseconds, $seconds) = explode(' ', microtime());
        $milliseconds = str_pad((string)floor((float)$microseconds * 1000), 3, '0', STR_PAD_LEFT);
        $dateTime = gmdate('Y-m-d\TH:i:s', (int)$seconds);
        return $dateTime . '.' . $milliseconds . 'Z';
    }

    /**
     * Realizar request autenticado con firma OKX
     * Reutiliza la lógica de OKXService pero adaptada para este servicio
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $method = strtoupper($method);
        // OKX requiere timestamp en formato ISO 8601 con milisegundos y zona UTC (formato: 2020-12-08T09:08:57.715Z)
        $timestamp = $this->getOKXTimestamp();
        $startTime = microtime(true);
        
        // Para GET, los parámetros van en query string, no en body
        // Para POST, los parámetros van en body como JSON
        $body = '';
        $requestPath = $endpoint;
        
        if ($method === 'GET' && !empty($params)) {
            // Construir query string y agregarlo al requestPath
            ksort($params);
            $queryString = http_build_query($params);
            $requestPath = $endpoint . '?' . $queryString;
        } elseif ($method !== 'GET' && !empty($params)) {
            // Para POST/PUT/DELETE, el body es JSON
            $body = json_encode($params, JSON_UNESCAPED_SLASHES);
        }
        
        // Generar firma: timestamp + method + requestPath + body
        // IMPORTANTE: Para GET, requestPath debe incluir los query parameters
        $message = $timestamp . $method . $requestPath . $body;
        $signature = base64_encode(hash_hmac('sha256', $message, $this->secretKey, true));
        
        $headers = [
            'OK-ACCESS-KEY' => $this->apiKey,
            'OK-ACCESS-SIGN' => $signature,
            'OK-ACCESS-TIMESTAMP' => $timestamp,
            'OK-ACCESS-PASSPHRASE' => $this->passphrase,
            'Content-Type' => 'application/json',
        ];
        
        // Si es testnet, agregar header especial
        if ($this->isTestnet) {
            $headers['x-simulated-trading'] = '1';
        }

        $url = $this->baseUrl . $requestPath;
        
        $response = null;
        
        if ($method === 'GET') {
            // Para GET, los parámetros van en la URL
            $response = Http::withHeaders($headers)->timeout(90)->get($url);
        } else {
            // Para POST/PUT/DELETE, enviar body como JSON
            $http = Http::withHeaders($headers);
            if (!empty($body)) {
                $http = $http->withBody($body, 'application/json');
            }
            
            $response = match($method) {
                'POST' => $http->timeout(90)->post($url),
                'PUT' => $http->timeout(90)->put($url),
                'DELETE' => $http->timeout(90)->delete($url),
                'PATCH' => $http->timeout(90)->patch($url),
                default => $http->timeout(90)->post($url),
            };
        }
        
        // Registrar respuesta en logs
        $responseTime = round((microtime(true) - $startTime) * 1000, 2); // en milisegundos
        
        try {
            $responseBody = $response->body();
            $responseJson = $response->json();
            $statusCode = $response->status();
            
            $logData = [
                'exchange' => 'okx',
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'params' => $params,
                'response_body' => $responseBody,
                'response_json' => $responseJson,
                'is_testnet' => $this->isTestnet,
                'user_id' => $this->userId,
            ];
            
            // Verificar tanto status HTTP como code en la respuesta
            // OKX puede devolver code como string '0' o integer 0
            $code = $responseJson['code'] ?? null;
            $isSuccess = $response->successful() && ($code === 0 || $code === '0');
            
            if ($isSuccess) {
                // Log exitoso con info
                Log::info('OKX API Response - Success', $logData);
            } else {
                // Log error con warning (incluye errores de code aunque status HTTP sea 200)
                Log::warning('OKX API Response - Error', array_merge($logData, [
                    'error_message' => $responseJson['msg'] ?? $responseJson['message'] ?? 'Unknown error',
                    'code' => $code,
                ]));
            }
        } catch (\Exception $e) {
            // Log si hay error al procesar la respuesta
            Log::error('OKX API Response - Parse Error', [
                'exchange' => 'okx',
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'status_code' => $response->status(),
                'response_time_ms' => $responseTime,
                'error' => $e->getMessage(),
                'response_body' => $response->body(),
                'user_id' => $this->userId,
            ]);
        }
        
        return $response;
    }

    /**
     * Mapear estados de P2P de OKX
     * Según información proporcionada: new, cancelled, completed
     */
    private function mapP2PStatus($orderStatus): string
    {
        if (empty($orderStatus)) {
            return 'pending';
        }
        
        $statusLower = strtolower((string)$orderStatus);
        
        return match($statusLower) {
            'new', 'pending', 'init' => 'pending',
            'completed', 'success', 'done', 'finished' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            'processing', 'in_progress', 'progress' => 'processing',
            'failed', 'fail' => 'failed',
            'expired', 'expire' => 'expired',
            default => 'pending'
        };
    }
}

