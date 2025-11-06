<?php

namespace App\Services;

use App\Models\BybitCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BybitService
{
    private const BASE_URL = 'https://api.bybit.com';
    private const TESTNET_BASE_URL = 'https://api-testnet.bybit.com';
    
    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private bool $isTestnet = false;
    private ?string $baseUrl = null;

    public function __construct(BybitCredential $credentials = null, array $directCredentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->isTestnet = $credentials->is_testnet;
            $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
        } elseif ($directCredentials) {
            $this->apiKey = $directCredentials['api_key'] ?? '';
            $this->secretKey = $directCredentials['secret_key'] ?? '';
            $this->isTestnet = $directCredentials['is_testnet'] ?? false;
            $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
        }
    }

    /**
     * Establecer credenciales manualmente
     */
    public function setCredentials(string $apiKey, string $secretKey, bool $isTestnet = false): void
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->isTestnet = $isTestnet;
        $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
    }

    /**
     * Verificar conexión con Bybit API
     * Usa el endpoint de información personal P2P para validar credenciales
     * Documentación: https://bybit-exchange.github.io/docs/p2p/user/acct-info
     */
    public function testConnection(): bool
    {
        try {
            // Validar que las credenciales estén configuradas
            if (empty($this->apiKey) || empty($this->secretKey)) {
                Log::error('Bybit connection test failed - Missing credentials', [
                    'has_api_key' => !empty($this->apiKey),
                    'has_secret_key' => !empty($this->secretKey),
                ]);
                return false;
            }
            
            // El endpoint requiere POST con body vacío {}
            $response = $this->makeAuthenticatedRequest('POST', '/v5/p2p/user/personal/info', []);
            
            if ($response->successful()) {
                $data = $response->json();
                // La respuesta usa ret_code (no retCode) según documentación
                return isset($data['ret_code']) && $data['ret_code'] == 0;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Bybit connection test failed', [
                'error' => $e->getMessage(),
                'endpoint' => '/v5/p2p/user/personal/info',
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Realizar request autenticado con firma
     * Según documentación de Bybit: https://bybit-exchange.github.io/docs/
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        // Obtener timestamp como string en milisegundos (mismo método que el ejemplo)
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
            $signatureString = $timestamp . $this->apiKey . $recvWindow . $queryString;
            
            $signature = hash_hmac('sha256', $signatureString, $this->secretKey);
            
            $headers['X-BAPI-SIGN'] = $signature;
            
            // Construir URL con query string
            $url = $this->baseUrl . $endpoint;
            if (!empty($queryString)) {
                $url .= '?' . $queryString;
            }
            
            $response = Http::withHeaders($headers)->timeout(10)->get($url);
            
        } else {
            // Para POST/PUT/DELETE: timestamp + api_key + recv_window + raw_json_body
            // Según el ejemplo: el signature_string debe usar el JSON body completo como string
            // Si params está vacío, enviar {} como objeto vacío
            $body = empty($params) ? (object)[] : $params;
            $jsonBody = json_encode($body);
            
            // El signature_string debe ser: timestamp + api_key + recv_window + raw_json_body
            $signatureString = $timestamp . $this->apiKey . $recvWindow . $jsonBody;
            
            $signature = hash_hmac('sha256', $signatureString, $this->secretKey);
            
            $headers['X-BAPI-SIGN'] = $signature;
            
            $url = $this->baseUrl . $endpoint;
            
            // Log para debugging
            Log::debug('Bybit API Request Details', [
                'endpoint' => $endpoint,
                'method' => $method,
                'url' => $url,
                'json_body' => $jsonBody,
                'signature_string' => $signatureString,
                'headers' => array_merge($headers, ['X-BAPI-SIGN' => '[HIDDEN]']),
            ]);
            
            $http = Http::withHeaders($headers)->withBody($jsonBody, 'application/json')->timeout(10);
            
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
            ]);
        }
        
        return $response;
    }

    /**
     * Obtener detalles completos de una orden P2P
     * Endpoint: POST /v5/p2p/order/info
     * Documentación: https://bybit-exchange.github.io/docs/p2p/order/order-detail
     * 
     * @param string $orderId ID de la orden
     * @return array|null Detalles de la orden o null si hay error
     */
    public function getOrderDetails(string $orderId): ?array
    {
        try {
            $response = $this->makeAuthenticatedRequest('POST', '/v5/p2p/order/info', [
                'orderId' => $orderId
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $retCode = $data['ret_code'] ?? $data['retCode'] ?? null;
                
                if ($retCode === 0 && isset($data['result'])) {
                    return $data['result'];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Bybit getOrderDetails failed', [
                'orderId' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Obtiene el timestamp actual en milisegundos como string.
     * Usa el mismo método que el ejemplo para evitar problemas de precisión.
     */
    private function getTimestamp(): string
    {
        // microtime(true) * 1000 puede dar problemas de precisión con números grandes
        // Usamos el método del ejemplo que separa segundos y microsegundos
        list($msec, $sec) = explode(' ', microtime());
        // $msec es algo como "0.123456", necesitamos los primeros 3 dígitos después del punto
        // substr($msec, 2, 3) toma desde la posición 2 (después de "0.") por 3 caracteres
        $msecStr = substr($msec, 2, 3);
        // Si tiene menos de 3 dígitos, rellenar con ceros
        $msecStr = str_pad($msecStr, 3, '0', STR_PAD_RIGHT);
        return $sec . $msecStr;
    }
}

