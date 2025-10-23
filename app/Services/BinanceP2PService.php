<?php

namespace App\Services;

use App\Models\BinanceCredential;
use App\Models\MarketData;
use App\Models\P2PAd;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BinanceP2PService
{
    private const BASE_URL = 'https://p2p.binance.com/bapi/c2c/v2';
    private const TESTNET_BASE_URL = 'https://testnet.binance.vision/bapi/c2c/v2';
    
    private string $apiKey;
    private string $secretKey;
    private bool $isTestnet;
    private string $baseUrl;

    public function __construct(BinanceCredential $credentials = null)
    {
        if ($credentials) {
            $this->apiKey = $credentials->api_key;
            $this->secretKey = $credentials->secret_key;
            $this->isTestnet = $credentials->is_testnet;
            $this->baseUrl = $this->isTestnet ? self::TESTNET_BASE_URL : self::BASE_URL;
        }
    }

    /**
     * Obtener detalles de un anuncio por número
     */
    public function getAdsDetailByNumber(string $adNumber): array
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . '/friendly/c2c/ad/search', [
                'adNumber' => $adNumber,
                'page' => 1,
                'rows' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Binance P2P API Error - getAdsDetailByNumber', [
                'adNumber' => $adNumber,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - getAdsDetailByNumber', [
                'adNumber' => $adNumber,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener precio de referencia
     */
    public function getAdsReferencePrice(string $fiat, string $asset): array
    {
        try {
            $cacheKey = "binance_reference_price_{$asset}_{$fiat}";
            
            return Cache::remember($cacheKey, 60, function () use ($fiat, $asset) {
                $response = Http::timeout(30)->get($this->baseUrl . '/friendly/c2c/portal/config', [
                    'fiat' => $fiat,
                    'asset' => $asset
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['data'] ?? [];
                }

                Log::error('Binance P2P API Error - getAdsReferencePrice', [
                    'fiat' => $fiat,
                    'asset' => $asset,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [];
            });
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - getAdsReferencePrice', [
                'fiat' => $fiat,
                'asset' => $asset,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Listar anuncios con paginación
     */
    public function getAdsList(array $params = []): array
    {
        try {
            $defaultParams = [
                'page' => 1,
                'rows' => 20,
                'asset' => 'BTC',
                'fiat' => 'COP',
                'tradeType' => 'BUY',
                'payTypes' => [],
                'countries' => [],
                'proMerchantAds' => false,
                'shieldMerchantAds' => false,
                'publisherType' => null,
                'classifies' => ['mass', 'profession']
            ];

            $params = array_merge($defaultParams, $params);

            $response = Http::timeout(30)->post($this->baseUrl . '/friendly/c2c/ad/search', $params);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Binance P2P API Error - getAdsList', [
                'params' => $params,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - getAdsList', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Buscar anuncios con condiciones específicas
     */
    public function searchAds(array $conditions): array
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . '/friendly/c2c/ad/search', $conditions);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Binance P2P API Error - searchAds', [
                'conditions' => $conditions,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - searchAds', [
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Crear nuevo anuncio (requiere autenticación)
     */
    public function postAd(array $data): array
    {
        try {
            $response = $this->makeAuthenticatedRequest('POST', '/c2c/v2/ad', $data);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Binance P2P API Error - postAd', [
                'data' => $data,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - postAd', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Actualizar anuncio existente (requiere autenticación)
     */
    public function updateAd(string $adNumber, array $data): array
    {
        try {
            $response = $this->makeAuthenticatedRequest('PUT', "/c2c/v2/ad/{$adNumber}", $data);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Binance P2P API Error - updateAd', [
                'adNumber' => $adNumber,
                'data' => $data,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - updateAd', [
                'adNumber' => $adNumber,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Cambiar estado del anuncio (requiere autenticación)
     */
    public function updateAdStatus(string $adNumber, string $status): array
    {
        try {
            $response = $this->makeAuthenticatedRequest('PUT', "/c2c/v2/ad/{$adNumber}/status", [
                'status' => $status
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Binance P2P API Error - updateAdStatus', [
                'adNumber' => $adNumber,
                'status' => $status,
                'response_status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - updateAdStatus', [
                'adNumber' => $adNumber,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener lista de criptomonedas disponibles
     */
    public function queryDigitalCurrencyList(): array
    {
        try {
            $cacheKey = 'binance_digital_currencies';
            
            return Cache::remember($cacheKey, 3600, function () {
                $response = Http::timeout(30)->get($this->baseUrl . '/friendly/c2c/portal/config');

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['data']['cryptoCurrencies'] ?? [];
                }

                Log::error('Binance P2P API Error - queryDigitalCurrencyList', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [];
            });
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - queryDigitalCurrencyList', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener lista de monedas fiat disponibles
     */
    public function queryFiatCurrencyList(): array
    {
        try {
            $cacheKey = 'binance_fiat_currencies';
            
            return Cache::remember($cacheKey, 3600, function () {
                $response = Http::timeout(30)->get($this->baseUrl . '/friendly/c2c/portal/config');

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['data']['fiatCurrencies'] ?? [];
                }

                Log::error('Binance P2P API Error - queryFiatCurrencyList', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [];
            });
        } catch (\Exception $e) {
            Log::error('Binance P2P Service Exception - queryFiatCurrencyList', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Sincronizar anuncios P2P y guardar en base de datos
     */
    public function syncP2PAds(string $asset = 'BTC', string $fiat = 'COP', int $limit = 50): int
    {
        $ads = $this->getAdsList([
            'asset' => $asset,
            'fiat' => $fiat,
            'rows' => $limit,
            'tradeType' => 'BUY'
        ]);

        $synced = 0;
        foreach ($ads as $adData) {
            try {
                P2PAd::updateOrCreate(
                    ['ad_number' => $adData['adNumber']],
                    [
                        'fiat' => $fiat,
                        'asset' => $asset,
                        'price' => $adData['price'],
                        'available_amount' => $adData['availableAmount'],
                        'min_limit' => $adData['minSingleTransAmount'],
                        'max_limit' => $adData['maxSingleTransAmount'],
                        'payment_methods' => $adData['payMethods'] ?? [],
                        'advertiser_id' => $adData['advertiser']['userNo'] ?? null,
                        'advertiser_nickname' => $adData['advertiser']['nickName'] ?? null,
                        'advertiser_month_finish_rate' => $adData['advertiser']['monthFinishRate'] ?? null,
                        'advertiser_month_order_count' => $adData['advertiser']['monthOrderCount'] ?? null,
                        'advertiser_month_finish_count' => $adData['advertiser']['monthFinishCount'] ?? null,
                        'status' => 'active',
                        'binance_updated_at' => now(),
                    ]
                );
                $synced++;
            } catch (\Exception $e) {
                Log::error('Error syncing P2P ad', [
                    'adNumber' => $adData['adNumber'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $synced;
    }

    /**
     * Actualizar datos de mercado
     */
    public function updateMarketData(string $asset = 'BTC', string $fiat = 'COP'): bool
    {
        try {
            $referencePrice = $this->getAdsReferencePrice($fiat, $asset);
            
            if (!empty($referencePrice)) {
                MarketData::create([
                    'asset' => $asset,
                    'fiat' => $fiat,
                    'price' => $referencePrice['price'] ?? 0,
                    'source' => 'binance_reference',
                    'metadata' => $referencePrice,
                    'data_timestamp' => now(),
                ]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Error updating market data', [
                'asset' => $asset,
                'fiat' => $fiat,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Realizar request autenticado con firma
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        $timestamp = time() * 1000;
        $queryString = http_build_query($data);
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);

        return Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->$method($this->baseUrl . $endpoint, array_merge($data, [
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]));
    }

    /**
     * Verificar conexión con Binance API
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/friendly/c2c/portal/config');
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Binance P2P connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
