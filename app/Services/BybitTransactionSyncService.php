<?php

namespace App\Services;

use App\Models\BybitCredential;
use App\Models\Transaction;
use App\Services\BybitService;
use App\Jobs\EnrichTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BybitTransactionSyncService
{
    private const BASE_URL = 'https://api.bybit.com';
    private const TESTNET_BASE_URL = 'https://api-testnet.bybit.com';
    
    private string $apiKey;
    private string $secretKey;
    private bool $isTestnet;
    private string $baseUrl;
    private ?int $userId;

    public function __construct(BybitCredential $credentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->isTestnet = $credentials->is_testnet;
            $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
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
            
            Log::info('Bybit Transaction Sync Completed', [
                'results' => $results,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Bybit Transaction Sync Failed', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            
            throw $e;
        }
    }

    /**
     * Sincronizar órdenes P2P de Bybit
     * Endpoint: /v5/p2p/order/simplifyList
     * Documentación: https://bybit-exchange.github.io/docs/p2p/order/order-list
     */
    public function syncP2POrders(Carbon $startTime = null, Carbon $endTime = null): int
    {
        $synced = 0;
        $startTime = $startTime ?? now()->subDays(30);
        $endTime = $endTime ?? now();

        try {
            // Obtener order_numbers de transacciones pendientes antes de sincronizar
            $pendingOrderNumbers = $this->getPendingOrderNumbers('p2p_order');
            
            $orders = $this->getP2POrderHistory($startTime, $endTime);
            
            // Crear instancia de BybitService para obtener detalles completos
            $bybitService = new BybitService();
            $bybitService->setCredentials($this->apiKey, $this->secretKey, $this->isTestnet);
            
            foreach ($orders as $order) {
                try {
                    // Extraer campos principales de la respuesta del API
                    // Según documentación: el campo es 'id' (no 'orderId')
                    $orderNumber = $order['id'] ?? $order['orderId'] ?? $order['order_id'] ?? null;
                    
                    if (!$orderNumber) {
                        Log::warning('Bybit P2P order missing id', ['order' => $order]);
                        continue;
                    }
                    
                    // Obtener detalles completos de la orden
                    $orderDetails = $bybitService->getOrderDetails((string)$orderNumber);
                    
                    // Combinar datos de la lista simplificada con los detalles completos
                    $fullOrder = $orderDetails ? array_merge($order, $orderDetails) : $order;
                    
                    // Mapear el tipo de orden (BUY/SELL)
                    // Según documentación: side es integer (0: Buy, 1: Sell)
                    $side = $fullOrder['side'] ?? null;
                    $tradeType = null;
                    if ($side === 0 || $side === '0') {
                        $tradeType = 'BUY';
                    } elseif ($side === 1 || $side === '1') {
                        $tradeType = 'SELL';
                    } else {
                        // Fallback para otros formatos
                        $tradeTypeStr = $fullOrder['type'] ?? null;
                        if ($tradeTypeStr === 'Buy' || $tradeTypeStr === 'BUY' || $tradeTypeStr === 'buy') {
                            $tradeType = 'BUY';
                        } elseif ($tradeTypeStr === 'Sell' || $tradeTypeStr === 'SELL' || $tradeTypeStr === 'sell') {
                            $tradeType = 'SELL';
                        }
                    }

                    // Mapear estado (es integer según documentación)
                    $newStatus = $this->mapP2PStatus($fullOrder['status'] ?? 'PENDING');
                    
                    // Verificar si esta orden estaba pendiente y ahora tiene un estado diferente
                    $wasPending = in_array((string)$orderNumber, $pendingOrderNumbers);
                    $shouldUpdate = $wasPending && $newStatus !== 'pending';

                    // Mapear fechas - createDate es timestamp en milisegundos como string
                    $createTime = now();
                    if (isset($fullOrder['createDate'])) {
                        $createTime = Carbon::createFromTimestamp((int)$fullOrder['createDate'] / 1000);
                    }
                    
                    // Mapear updateDate si existe
                    $updateTime = $createTime;
                    if (isset($fullOrder['updateDate'])) {
                        $updateTime = Carbon::createFromTimestamp((int)$fullOrder['updateDate'] / 1000);
                    }
                    
                    // Extraer información del método de pago
                    $paymentMethod = null;
                    $paymentType = $fullOrder['paymentType'] ?? null;
                    
                    if ($paymentType !== null) {
                        // Buscar en paymentTermList el objeto que coincida con el paymentType
                        $paymentTermList = $fullOrder['paymentTermList'] ?? [];
                        if (is_array($paymentTermList) && !empty($paymentTermList)) {
                            foreach ($paymentTermList as $paymentTerm) {
                                if (isset($paymentTerm['paymentType']) && 
                                    (int)$paymentTerm['paymentType'] === (int)$paymentType) {
                                    // Extraer paymentName de paymentConfigVo
                                    if (isset($paymentTerm['paymentConfigVo']['paymentName']) && 
                                        !empty($paymentTerm['paymentConfigVo']['paymentName'])) {
                                        $paymentMethod = $paymentTerm['paymentConfigVo']['paymentName'];
                                        break;
                                    }
                                    // Fallback: usar bankName si existe
                                    if (!$paymentMethod && isset($paymentTerm['bankName']) && 
                                        !empty($paymentTerm['bankName'])) {
                                        $paymentMethod = $paymentTerm['bankName'];
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Si no se encontró en paymentTermList, buscar en confirmedPayTerm
                        if (!$paymentMethod) {
                            $confirmedPayTerm = $fullOrder['confirmedPayTerm'] ?? null;
                            if ($confirmedPayTerm && is_array($confirmedPayTerm)) {
                                if (isset($confirmedPayTerm['paymentType']) && 
                                    (int)$confirmedPayTerm['paymentType'] === (int)$paymentType) {
                                    // Extraer paymentName de paymentConfigVo
                                    if (isset($confirmedPayTerm['paymentConfigVo']['paymentName']) && 
                                        !empty($confirmedPayTerm['paymentConfigVo']['paymentName'])) {
                                        $paymentMethod = $confirmedPayTerm['paymentConfigVo']['paymentName'];
                                    }
                                    // Fallback: usar bankName si existe
                                    if (!$paymentMethod && isset($confirmedPayTerm['bankName']) && 
                                        !empty($confirmedPayTerm['bankName'])) {
                                        $paymentMethod = $confirmedPayTerm['bankName'];
                                    }
                                }
                            }
                        }
                    }
                    
                    // Extraer información de la contraparte
                    $counterPartyNickName = $fullOrder['targetNickName'] ?? $fullOrder['counterPartyNickName'] ?? $fullOrder['counterPartyNickname'] ?? $fullOrder['counterParty'] ?? null;
                    $counterPartyFullName = null;
                    $counterPartyDni = null;
                    $dniType = null;
                    
                    // Si es compra, el vendedor es la contraparte
                    if ($tradeType === 'BUY') {
                        $counterPartyFullName = $fullOrder['sellerRealName'] ?? null;
                    } elseif ($tradeType === 'SELL') {
                        $counterPartyFullName = $fullOrder['buyerRealName'] ?? null;
                    }
                    
                    // Determinar cantidad y amount correctamente
                    // Según documentación: quantity es la cantidad de crypto, amount es el total en fiat
                    $quantity = isset($fullOrder['quantity']) ? (float)$fullOrder['quantity'] : 0;
                    $amount = isset($fullOrder['amount']) ? (float)$fullOrder['amount'] : 0;
                    $price = isset($fullOrder['price']) ? (float)$fullOrder['price'] : 0;
                    
                    // Si no hay amount pero hay quantity y price, calcularlo
                    if ($amount == 0 && $quantity > 0 && $price > 0) {
                        $amount = $quantity * $price;
                    }
                    
                    // Extraer fees
                    $makerFee = isset($fullOrder['makerFee']) ? (float)$fullOrder['makerFee'] : 0;
                    $takerFee = isset($fullOrder['takerFee']) ? (float)$fullOrder['takerFee'] : 0;
                    $commission = max($makerFee, $takerFee, isset($fullOrder['fee']) ? (float)$fullOrder['fee'] : 0);

                    $transaction = Transaction::updateOrCreate(
                        [
                            'order_number' => (string) $orderNumber,
                            'transaction_type' => 'p2p_order',
                            'user_id' => $this->userId,
                        ],
                        [
                            'user_id' => $this->userId,
                            'exchange' => 'bybit',
                            'asset_type' => $fullOrder['tokenId'] ?? $fullOrder['coin'] ?? $fullOrder['crypto'] ?? $fullOrder['asset'] ?? null,
                            'fiat_type' => $fullOrder['currencyId'] ?? $fullOrder['fiat'] ?? $fullOrder['currency'] ?? null,
                            'order_type' => $tradeType ?? 'UNKNOWN',
                            'quantity' => $quantity,
                            'price' => $price,
                            'amount' => $amount,
                            'total_price' => $amount,
                            'taker_fee' => $takerFee,
                            'commission' => $commission,
                            'payment_method' => $paymentMethod,
                            'counter_party' => $counterPartyNickName,
                            'counter_party_full_name' => $counterPartyFullName,
                            'counter_party_dni' => $counterPartyDni,
                            'dni_type' => $dniType,
                            'status' => $newStatus,
                            'binance_create_time' => $createTime,
                            'binance_update_time' => $updateTime,
                            'source_endpoint' => '/v5/p2p/order/info',
                            'metadata' => $fullOrder,
                            'notes' => $fullOrder['remark'] ?? $fullOrder['cancelReason'] ?? null,
                            'last_synced_at' => now(),
                        ]
                    );
                    
                    // Si era pendiente y ahora tiene un estado diferente, loguear la actualización
                    if ($shouldUpdate) {
                        Log::info('Pending P2P order status updated', [
                            'transaction_id' => $transaction->id,
                            'order_number' => $orderNumber,
                            'old_status' => 'pending',
                            'new_status' => $newStatus
                        ]);
                    }
                    
                    // Despachar job para enriquecimiento en background solo si:
                    // 1. La transacción es nueva (wasRecentlyCreated), O
                    // 2. La transacción está pendiente y necesita actualización
                    if ($orderNumber) {
                        $shouldEnrich = false;
                        
                        // Verificar si es nueva o necesita enriquecimiento
                        if ($transaction->wasRecentlyCreated) {
                            $shouldEnrich = true;
                            Log::debug('New transaction detected, will enrich', [
                                'transaction_id' => $transaction->id,
                                'order_number' => $orderNumber
                            ]);
                        } elseif (in_array($newStatus, ['pending', 'processing']) && empty($transaction->counter_party)) {
                            // Si está pendiente y no tiene counter_party, necesita enriquecimiento
                            $shouldEnrich = true;
                            Log::debug('Pending transaction without counter_party, will enrich', [
                                'transaction_id' => $transaction->id,
                                'order_number' => $orderNumber,
                                'status' => $newStatus
                            ]);
                        }
                        
                        if ($shouldEnrich) {
                            try {
                                // Marcar transacción como pending para enriquecimiento
                                if (Schema::hasColumn('transactions', 'enrichment_status')) {
                                    $transaction->update(['enrichment_status' => 'pending']);
                                }
                                
                                EnrichTransaction::dispatch($transaction->id);
                                Log::debug('EnrichTransaction job dispatched for Bybit transaction', [
                                    'transaction_id' => $transaction->id,
                                    'order_number' => $orderNumber
                                ]);
                            } catch (\Exception $e) {
                                Log::warning('Error dispatching EnrichTransaction job', [
                                    'transaction_id' => $transaction->id,
                                    'order_number' => $orderNumber,
                                    'error' => $e->getMessage()
                                ]);
                                // Continuar aunque falle el despacho del job
                            }
                        }
                    }
                    
                    if ($transaction->wasRecentlyCreated) {
                        $synced++;
                    }
                    
                    // Rate limiting: pequeño delay entre órdenes
                    usleep(100000); // 100ms
                } catch (\Exception $e) {
                    Log::error('Error processing individual Bybit P2P order', [
                        'order' => $order,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    continue;
                }
            }
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Error syncing Bybit P2P orders', [
                'error' => $e->getMessage(),
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);
            return 0;
        }
    }

    /**
     * Obtener historial de órdenes P2P de Bybit
     * Endpoint: POST /v5/p2p/order/simplifyList
     * Documentación: https://bybit-exchange.github.io/docs/p2p/order/order-list
     */
    private function getP2POrderHistory(Carbon $startTime, Carbon $endTime): array
    {
        $allOrders = [];
        
        try {
            $hasMore = true;
            $page = 1;
            $size = 10; // Filas por página
            
            while ($hasMore) {
                // Construir parámetros según documentación
                // Según el ejemplo de la documentación, todos los parámetros opcionales deben estar presentes como null
                // El orden debe ser: status, beginTime, endTime, tokenId, side, page, size (según ejemplo de documentación)
                // page y size son obligatorios (integers)
                // beginTime y endTime son opcionales (timestamps en milisegundos como strings)
                $requestParams = [
                    //'status' => null,
                    'beginTime' => $startTime ? (string)($startTime->timestamp * 1000) : null,
                    'endTime' => $endTime ? (string)($endTime->timestamp * 1000) : null,
                    //'tokenId' => null,
                    //'side' => null,
                    'page' => $page,
                    'size' => $size,
                ];
                
                // Log detallado del request antes de enviarlo
                Log::debug('Bybit P2P Order Request', [
                    'endpoint' => '/v5/p2p/order/simplifyList',
                    'params' => $requestParams,
                    'params_json' => json_encode($requestParams),
                ]);
                
                $response = $this->makeAuthenticatedRequest('POST', '/v5/p2p/order/simplifyList', $requestParams);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Verificar ret_code (puede ser ret_code o retCode)
                    $retCode = $data['ret_code'] ?? $data['retCode'] ?? null;
                    
                    if ($retCode === 0) {
                        $result = $data['result'] ?? [];
                        // Según documentación: la respuesta usa 'items' (no 'list')
                        $orders = $result['items'] ?? [];
                        $totalCount = $result['count'] ?? 0;
                        
                        $allOrders = array_merge($allOrders, $orders);
                        
                        // Verificar si hay más páginas
                        $currentCount = count($allOrders);
                        if ($currentCount < $totalCount && !empty($orders)) {
                            $page++;
                            
                            // Rate limiting: delay entre páginas
                            usleep(200000); // 200ms
                        } else {
                            $hasMore = false;
                        }
                    } else {
                        $retMsg = $data['ret_msg'] ?? $data['retMsg'] ?? 'Unknown error';
                        Log::warning('Bybit API returned error response for P2P orders', [
                            'ret_code' => $retCode,
                            'ret_msg' => $retMsg,
                            'page' => $page
                        ]);
                        $hasMore = false;
                    }
                } else {
                    Log::warning('Bybit API returned unsuccessful response for P2P orders', [
                        'endpoint' => '/v5/p2p/order/simplifyList',
                        'page' => $page,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    $hasMore = false;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error fetching P2P orders from Bybit API', [
                'endpoint' => '/v5/p2p/order/simplifyList',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return $allOrders;
    }

    /**
     * Obtiene el timestamp actual en milisegundos como string.
     * Usa el mismo método que BybitService para evitar problemas de precisión.
     */
    private function getTimestamp(): string
    {
        // microtime(true) * 1000 puede dar problemas de precisión con números grandes
        // Usamos el método que separa segundos y microsegundos
        list($msec, $sec) = explode(' ', microtime());
        // $msec es algo como "0.123456", necesitamos los primeros 3 dígitos después del punto
        $msecStr = substr($msec, 2, 3);
        // Si tiene menos de 3 dígitos, rellenar con ceros
        $msecStr = str_pad($msecStr, 3, '0', STR_PAD_RIGHT);
        return $sec . $msecStr;
    }

    /**
     * Mapear tipo de pago a nombre legible
     */
    private function mapPaymentType($paymentType): ?string
    {
        // Los tipos de pago son números según la documentación
        // Algunos valores comunes: 1=Bank transfer, 2=Alipay, etc.
        // Por ahora retornamos el número, pero se puede mejorar con un mapeo completo
        return $paymentType !== null ? (string)$paymentType : null;
    }

    /**
     * Realizar request autenticado con firma
     * Según documentación de Bybit: https://bybit-exchange.github.io/docs/
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        // Obtener timestamp como string en milisegundos (mismo método que BybitService)
        $timestamp = $this->getTimestamp();
        $recvWindow = 5000;
        $startTime = microtime(true);
        
        $method = strtoupper($method);
        
        // Preparar headers base
        $headers = [
            'X-BAPI-API-KEY' => $this->apiKey,
            'X-BAPI-TIMESTAMP' => $timestamp,
            'X-BAPI-RECV-WINDOW' => (string)$recvWindow,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        
        $response = null;
        
        // Generar firma según el método
        if ($method === 'GET') {
            // Para GET: timestamp + api_key + recv_window + queryString
            ksort($params);
            $queryString = http_build_query($params);
            // Asegurar que todos los componentes del signature_string sean strings explícitos
            $signatureString = $timestamp . $this->apiKey . (string)$recvWindow . $queryString;
            
            $signature = hash_hmac('sha256', $signatureString, $this->secretKey);
            
            $headers['X-BAPI-SIGN'] = $signature;
            
            // Construir URL con query string
            $url = $this->baseUrl . $endpoint;
            if (!empty($queryString)) {
                $url .= '?' . $queryString;
            }
            
            $response = Http::withHeaders($headers)->timeout(90)->get($url);
            
        } else {
            // Para POST/PUT/DELETE: timestamp + api_key + recv_window + raw_json_body
            // Según el ejemplo: el signature_string debe usar el JSON body completo como string
            // Si params está vacío, enviar {} como objeto vacío
            $body = empty($params) ? (object)[] : $params;
            $jsonBody = json_encode($body);
            
            // El signature_string debe ser: timestamp + api_key + recv_window + raw_json_body
            $signatureString = (string)$timestamp . $this->apiKey . (string)$recvWindow . $jsonBody;
            
            $signature = hash_hmac('sha256', $signatureString, $this->secretKey);
            
            $headers['X-BAPI-SIGN'] = $signature;
            $headers['Content-Type'] = 'application/json';
            $headers['Accept'] = 'application/json';
            $url = $this->baseUrl . $endpoint;
            
            // Log detallado del request completo para debug
            Log::debug('Bybit API Request Details', [
                'endpoint' => $endpoint,
                'method' => $method,
                'url' => $url,
                'json_body' => $jsonBody,
                'signature_string' => $signatureString,
                'headers' => array_merge($headers, ['X-BAPI-SIGN' => '[HIDDEN]']),
            ]);
            
            $http = Http::withHeaders($headers)->withBody($jsonBody, 'application/json')->timeout(90);
            
            $response = match($method) {
                'POST' => $http->post($url),
                'PUT' => $http->put($url),
                'DELETE' => $http->delete($url),
                'PATCH' => $http->patch($url),
                default => $http->post($url),
            };
        }
        
        // Registrar respuesta en logs
        $responseTime = round((microtime(true) - $startTime) * 1000, 2); // en milisegundos
        
        try {
            $responseBody = $response->body();
            $responseJson = $response->json();
            $statusCode = $response->status();
            
            $logData = [
                'exchange' => 'bybit',
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
            
            // Verificar tanto status HTTP como ret_code en la respuesta
            $retCode = $responseJson['ret_code'] ?? $responseJson['retCode'] ?? null;
            $isSuccess = $response->successful() && $retCode === 0;
            
            if ($isSuccess) {
                // Log exitoso con info
                Log::info('Bybit API Response - Success', $logData);
            } else {
                // Log error con warning (incluye errores de ret_code aunque status HTTP sea 200)
                Log::warning('Bybit API Response - Error', array_merge($logData, [
                    'error_message' => $responseJson['ret_msg'] ?? $responseJson['retMsg'] ?? $responseJson['msg'] ?? 'Unknown error',
                    'ret_code' => $retCode,
                ]));
            }
        } catch (\Exception $e) {
            // Log si hay error al procesar la respuesta
            Log::error('Bybit API Response - Parse Error', [
                'exchange' => 'bybit',
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
     * Mapear estados de P2P de Bybit
     * Según documentación: https://bybit-exchange.github.io/docs/p2p/order/order-list
     * Los estados son códigos numéricos
     */
    private function mapP2PStatus($status): string
    {
        // Convertir a integer si es numérico
        $statusInt = is_numeric($status) ? (int)$status : null;
        
        // Si es un código numérico, mapearlo según documentación
        if ($statusInt !== null) {
            return match($statusInt) {
                5 => 'pending',      // waiting for chain (web3)
                10 => 'pending',     // waiting for buyer to pay
                20 => 'processing',  // waiting for seller to release
                30 => 'processing',  // appealing
                40 => 'cancelled',   // order cancelled
                50 => 'completed',   // order finished
                60 => 'processing',  // paying (only when paying online)
                70 => 'failed',      // pay fail (only when paying online)
                80 => 'cancelled',   // exception cancelled
                90 => 'pending',     // waiting for buyer to select tokenId
                100 => 'processing', // objectioning
                110 => 'pending',    // waiting for the user to raise an objection
                default => 'pending'
            };
        }
        
        // Si es string, mapear como antes para compatibilidad
        $statusUpper = strtoupper((string)$status);
        
        return match($statusUpper) {
            'PENDING', 'INIT' => 'pending',
            'PROCESSING', 'IN_PROGRESS' => 'processing',
            'COMPLETED', 'SUCCESS', 'DONE' => 'completed',
            'CANCELLED', 'CANCELED' => 'cancelled',
            'FAILED', 'FAIL' => 'failed',
            'EXPIRED', 'EXPIRE' => 'expired',
            default => 'pending'
        };
    }

    /**
     * Enriquecer una transacción P2P con información adicional del detalle de la orden
     * 
     * Para Bybit, los detalles ya se obtienen en syncP2POrders, pero este método
     * permite refrescar los detalles si es necesario o actualizar información adicional.
     */
    public function enrichP2POrderWithDetail(Transaction $transaction): void
    {
        try {
            // Obtener el order_number de la transacción
            $orderNumber = $transaction->order_number;

            if (!$orderNumber) {
                Log::warning('Bybit enrichP2POrderWithDetail: Missing order_number', [
                    'transaction_id' => $transaction->id
                ]);
                return;
            }

            // Crear instancia de BybitService para obtener detalles completos
            $bybitService = new BybitService();
            $bybitService->setCredentials($this->apiKey, $this->secretKey, $this->isTestnet);

            // Obtener detalles actualizados de la orden
            $orderDetails = $bybitService->getOrderDetails((string)$orderNumber);

            if (!$orderDetails) {
                Log::warning('Bybit enrichP2POrderWithDetail: Could not get order details', [
                    'transaction_id' => $transaction->id,
                    'order_number' => $orderNumber
                ]);
                return;
            }

            // Actualizar metadata con los detalles actualizados
            $metadata = $transaction->metadata ?? [];
            $metadata['order_detail'] = $orderDetails;
            $metadata['enriched_at'] = now()->toIso8601String();

            // Actualizar campos que puedan haber cambiado
            $updateData = [
                'metadata' => $metadata,
            ];

            // Si hay información de contraparte en los detalles, actualizarla
            $counterPartyNickName = $orderDetails['targetNickName'] ?? $orderDetails['counterPartyNickName'] ?? $orderDetails['counterPartyNickname'] ?? null;
            if ($counterPartyNickName) {
                $updateData['counter_party'] = $counterPartyNickName;
            }

            // Actualizar información de contraparte según el tipo de orden
            $tradeType = $transaction->order_type;
            if ($tradeType === 'BUY') {
                $counterPartyFullName = $orderDetails['sellerRealName'] ?? null;
                if ($counterPartyFullName) {
                    $updateData['counter_party_full_name'] = $counterPartyFullName;
                }
            } elseif ($tradeType === 'SELL') {
                $counterPartyFullName = $orderDetails['buyerRealName'] ?? null;
                if ($counterPartyFullName) {
                    $updateData['counter_party_full_name'] = $counterPartyFullName;
                }
            }

            // Actualizar estado si ha cambiado
            $newStatus = $this->mapP2PStatus($orderDetails['status'] ?? $transaction->status);
            if ($newStatus !== $transaction->status) {
                $updateData['status'] = $newStatus;
            }

            // Actualizar la transacción
            $transaction->update($updateData);

            Log::info('Bybit enrichP2POrderWithDetail: Transaction enriched successfully', [
                'transaction_id' => $transaction->id,
                'order_number' => $orderNumber,
                'updated_fields' => array_keys($updateData)
            ]);

        } catch (\Exception $e) {
            Log::error('Bybit enrichP2POrderWithDetail: Error enriching transaction', [
                'transaction_id' => $transaction->id,
                'order_number' => $transaction->order_number ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener los order_numbers de transacciones pendientes para un tipo de transacción
     */
    private function getPendingOrderNumbers(string $transactionType): array
    {
        if (!$this->userId) {
            return [];
        }

        try {
            // Obtener order_numbers de transacciones pendientes o en procesamiento
            // Limitamos a las últimas 90 días para evitar consultar demasiadas transacciones antiguas
            $cutoffDate = now()->subDays(90);
            
            $pendingTransactions = Transaction::where('user_id', $this->userId)
                ->where('exchange', 'bybit')
                ->where('transaction_type', $transactionType)
                ->whereIn('status', ['pending', 'processing'])
                ->where('binance_create_time', '>=', $cutoffDate)
                ->whereNotNull('order_number')
                ->pluck('order_number')
                ->toArray();

            return $pendingTransactions;
        } catch (\Exception $e) {
            Log::warning('Error getting pending order numbers', [
                'user_id' => $this->userId,
                'transaction_type' => $transactionType,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}

