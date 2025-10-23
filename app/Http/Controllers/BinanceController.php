<?php

namespace App\Http\Controllers;

use App\Models\BinanceCredential;
use App\Models\P2PAd;
use App\Models\MarketData;
use App\Services\BinanceP2PService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BinanceController extends Controller
{
    /**
     * Display Binance API configuration page.
     */
    public function config()
    {
        return inertia('Binance/Config');
    }

    /**
     * Store Binance API credentials.
     */
    public function storeCredentials(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'testnet' => 'boolean',
        ]);

        try {
            $credentials = BinanceCredential::updateOrCreate(
                ['user_id' => Auth::id()],
                [
                    'api_key' => $request->api_key,
                    'secret_key' => $request->secret_key,
                    'is_testnet' => $request->boolean('testnet', false),
                    'is_active' => true,
                ]
            );

            // Test connection
            $service = new BinanceP2PService($credentials);
            $isConnected = $service->testConnection();

            if (!$isConnected) {
                $credentials->is_active = false;
                $credentials->last_error = 'Connection test failed';
                $credentials->save();

                return redirect()->back()->with('error', 'Las credenciales no son válidas o hay un problema de conexión.');
            }

            $credentials->last_used_at = now();
            $credentials->last_error = null;
            $credentials->save();

            return redirect()->back()->with('success', 'Credenciales guardadas y verificadas exitosamente!');
        } catch (\Exception $e) {
            Log::error('Error storing Binance credentials', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Error al guardar las credenciales.');
        }
    }

    /**
     * Fetch account information from Binance.
     */
    public function accountInfo()
    {
        // TODO: Implement Binance API integration
        return response()->json([
            'message' => 'Binance API integration coming soon!'
        ]);
    }

    /**
     * Fetch trading history from Binance.
     */
    public function tradingHistory()
    {
        // TODO: Implement trading history fetching
        return response()->json([
            'message' => 'Trading history integration coming soon!'
        ]);
    }

    /**
     * Obtener detalles de anuncio por número
     */
    public function getAdDetails(Request $request)
    {
        $request->validate([
            'ad_number' => 'required|string'
        ]);

        try {
            $credentials = BinanceCredential::where('user_id', Auth::id())
                ->where('is_active', true)
                ->first();

            if (!$credentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay credenciales de Binance configuradas'
                ], 400);
            }

            $service = new BinanceP2PService($credentials);
            $adDetails = $service->getAdsDetailByNumber($request->ad_number);

            if (empty($adDetails)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el anuncio especificado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $adDetails
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting ad details', [
                'user_id' => Auth::id(),
                'ad_number' => $request->ad_number,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles del anuncio'
            ], 500);
        }
    }

    /**
     * Obtener precio de referencia
     */
    public function getReferencePrice($fiat, $asset)
    {
        try {
            $credentials = BinanceCredential::where('user_id', Auth::id())
                ->where('is_active', true)
                ->first();

            if (!$credentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay credenciales de Binance configuradas'
                ], 400);
            }

            $service = new BinanceP2PService($credentials);
            $referencePrice = $service->getAdsReferencePrice($fiat, $asset);

            return response()->json([
                'success' => true,
                'data' => $referencePrice
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting reference price', [
                'user_id' => Auth::id(),
                'fiat' => $fiat,
                'asset' => $asset,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el precio de referencia'
            ], 500);
        }
    }

    /**
     * Buscar anuncios competidores
     */
    public function searchCompetitors(Request $request)
    {
        $request->validate([
            'asset' => 'required|string',
            'fiat' => 'required|string',
            'trade_type' => 'required|in:BUY,SELL',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $credentials = BinanceCredential::where('user_id', Auth::id())
                ->where('is_active', true)
                ->first();

            if (!$credentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay credenciales de Binance configuradas'
                ], 400);
            }

            $service = new BinanceP2PService($credentials);
            $competitors = $service->getAdsList([
                'asset' => $request->asset,
                'fiat' => $request->fiat,
                'tradeType' => $request->trade_type,
                'rows' => $request->input('limit', 20)
            ]);

            return response()->json([
                'success' => true,
                'data' => $competitors
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching competitors', [
                'user_id' => Auth::id(),
                'params' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar competidores'
            ], 500);
        }
    }

    /**
     * Obtener datos de mercado actuales
     */
    public function getMarketData(Request $request)
    {
        $request->validate([
            'asset' => 'nullable|string',
            'fiat' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $query = MarketData::query();

            if ($request->asset) {
                $query->where('asset', $request->asset);
            }

            if ($request->fiat) {
                $query->where('fiat', $request->fiat);
            }

            $marketData = $query->latest('data_timestamp')
                ->limit($request->input('limit', 50))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $marketData
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting market data', [
                'user_id' => Auth::id(),
                'params' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de mercado'
            ], 500);
        }
    }

    /**
     * Obtener anuncios P2P almacenados localmente
     */
    public function getP2PAds(Request $request)
    {
        $request->validate([
            'asset' => 'nullable|string',
            'fiat' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,deleted',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $query = P2PAd::query();

            if ($request->asset) {
                $query->where('asset', $request->asset);
            }

            if ($request->fiat) {
                $query->where('fiat', $request->fiat);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $ads = $query->orderBy('position', 'asc')
                ->limit($request->input('limit', 50))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ads
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting P2P ads', [
                'user_id' => Auth::id(),
                'params' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener anuncios P2P'
            ], 500);
        }
    }

    /**
     * Sincronizar anuncios P2P desde Binance
     */
    public function syncP2PAds(Request $request)
    {
        $request->validate([
            'asset' => 'required|string',
            'fiat' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $credentials = BinanceCredential::where('user_id', Auth::id())
                ->where('is_active', true)
                ->first();

            if (!$credentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay credenciales de Binance configuradas'
                ], 400);
            }

            $service = new BinanceP2PService($credentials);
            $synced = $service->syncP2PAds(
                $request->asset,
                $request->fiat,
                $request->input('limit', 50)
            );

            return response()->json([
                'success' => true,
                'message' => "Se sincronizaron {$synced} anuncios",
                'synced_count' => $synced
            ]);
        } catch (\Exception $e) {
            Log::error('Error syncing P2P ads', [
                'user_id' => Auth::id(),
                'params' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar anuncios P2P'
            ], 500);
        }
    }
}
