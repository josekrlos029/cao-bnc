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
    private bool $isTestnet = false;
    private ?string $baseUrl = null;

    public function __construct(OKXCredential $credentials = null, array $directCredentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->isTestnet = $credentials->is_testnet;
            // OKX usa el mismo dominio pero con diferentes headers para testnet
            $this->baseUrl = self::BASE_URL;
        } elseif ($directCredentials) {
            $this->apiKey = $directCredentials['api_key'] ?? '';
            $this->secretKey = $directCredentials['secret_key'] ?? '';
            $this->isTestnet = $directCredentials['is_testnet'] ?? false;
            // OKX usa el mismo dominio pero con diferentes headers para testnet
            $this->baseUrl = self::BASE_URL;
        }
    }

    /**
     * Verificar conexión con OKX API
     * Usa el endpoint de account balance para validar credenciales
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeAuthenticatedRequest('GET', '/api/v5/account/balance');
            
            if ($response->successful()) {
                $data = $response->json();
                return isset($data['code']) && $data['code'] == '0';
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('OKX connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Realizar request autenticado con firma OKX
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $timestamp = now()->toIso8601String();
        
        // OKX requiere un formato específico para la firma
        $body = !empty($params) ? json_encode($params, JSON_UNESCAPED_SLASHES) : '';
        $message = $timestamp . $method . $endpoint . $body;
        
        // Crear firma usando HMAC SHA256
        $signature = base64_encode(hash_hmac('sha256', $message, $this->secretKey, true));
        
        $headers = [
            'OK-ACCESS-KEY' => $this->apiKey,
            'OK-ACCESS-SIGN' => $signature,
            'OK-ACCESS-TIMESTAMP' => $timestamp,
            'OK-ACCESS-PASSPHRASE' => '', // Opcional, puede requerirse según configuración
            'Content-Type' => 'application/json',
        ];
        
        // Si es testnet, agregar header especial
        if ($this->isTestnet) {
            $headers['x-simulated-trading'] = '1';
        }

        $url = $this->baseUrl . $endpoint;
        
        return Http::withHeaders($headers)
            ->timeout(10)
            ->$method($url, $params);
    }
}

