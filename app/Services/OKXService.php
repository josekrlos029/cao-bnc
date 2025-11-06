<?php

namespace App\Services;

use App\Models\OKXCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OKXService
{
    private const BASE_URL = 'https://www.okx.com';
    private const TESTNET_BASE_URL = 'https://www.okx.com';
    
    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private ?string $passphrase = null;
    private bool $isTestnet = false;
    private ?string $baseUrl = null;

    public function __construct(OKXCredential $credentials = null, array $directCredentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->passphrase = $credentials->passphrase;
            $this->isTestnet = $credentials->is_testnet;
            // OKX usa el mismo dominio pero con diferentes headers para testnet
            $this->baseUrl = self::BASE_URL;
        } elseif ($directCredentials) {
            $this->apiKey = $directCredentials['api_key'] ?? '';
            $this->secretKey = $directCredentials['secret_key'] ?? '';
            $this->passphrase = $directCredentials['passphrase'] ?? '';
            $this->isTestnet = $directCredentials['is_testnet'] ?? false;
            // OKX usa el mismo dominio pero con diferentes headers para testnet
            $this->baseUrl = self::BASE_URL;
        }
    }

    /**
     * Verificar conexión con OKX API
     * Usa el endpoint GET /api/v5/p2p/user/basic-info para validar credenciales
     * Documentación: https://www.okx.com/docs-v5/en/#rest-api-p2p-get-basic-info
     */
    public function testConnection(): bool
    {
        try {
            // Validar que las credenciales estén configuradas
            if (empty($this->apiKey) || empty($this->secretKey) || empty($this->passphrase)) {
                Log::error('OKX connection test failed - Missing credentials', [
                    'has_api_key' => !empty($this->apiKey),
                    'has_secret_key' => !empty($this->secretKey),
                    'has_passphrase' => !empty($this->passphrase),
                ]);
                return false;
            }
            
            $response = $this->makeAuthenticatedRequest('GET', '/api/v5/p2p/user/basic-info');
            
            if ($response->successful()) {
                $data = $response->json();
                // OKX usa 'code' con valor 0 o '0' para éxito
                $code = $data['code'] ?? null;
                return isset($code) && ($code === 0 || $code === '0');
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('OKX connection test failed', [
                'error' => $e->getMessage(),
                'endpoint' => '/api/v5/p2p/user/basic-info',
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Realizar request autenticado con firma OKX
     * Documentación: https://www.okx.com/docs-v5/en/#overview-rest-authentication
     * 
     * Firma: timestamp + method + requestPath + body → HMAC SHA256 → Base64
     */
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
            'OK-ACCESS-PASSPHRASE' => $this->passphrase ?? '',
            'Content-Type' => 'application/json',
        ];
        
        // Si es testnet, agregar header especial
        if ($this->isTestnet) {
            $headers['x-simulated-trading'] = '1';
        }

        $url = $this->baseUrl . $requestPath;
        
        // Log para debugging
        Log::debug('OKX API Request Details', [
            'endpoint' => $endpoint,
            'method' => $method,
            'url' => $url,
            'request_path' => $requestPath,
            'body' => $body,
            'message' => $message,
            'headers' => array_merge($headers, ['OK-ACCESS-SIGN' => '[HIDDEN]', 'OK-ACCESS-PASSPHRASE' => '[HIDDEN]']),
        ]);
        
        $response = null;
        
        if ($method === 'GET') {
            // Para GET, los parámetros van en la URL
            $response = Http::withHeaders($headers)->timeout(30)->get($url);
        } else {
            // Para POST/PUT/DELETE, enviar body como JSON
            $http = Http::withHeaders($headers);
            if (!empty($body)) {
                $http = $http->withBody($body, 'application/json');
            }
            
            $response = match($method) {
                'POST' => $http->timeout(30)->post($url),
                'PUT' => $http->timeout(30)->put($url),
                'DELETE' => $http->timeout(30)->delete($url),
                'PATCH' => $http->timeout(30)->patch($url),
                default => $http->timeout(30)->post($url),
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
            ]);
        }
        
        return $response;
    }
}

