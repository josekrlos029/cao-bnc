<?php

namespace App\Http\Controllers;

use App\Models\BinanceCredential;
use App\Models\BybitCredential;
use App\Models\OKXCredential;
use App\Services\BinanceP2PService;
use App\Services\BybitService;
use App\Services\OKXService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ExchangeCredentialsController extends Controller
{
    /**
     * Display exchange credentials configuration page.
     */
    public function index()
    {
        $user = Auth::user();
        
        $binanceCredential = BinanceCredential::where('user_id', $user->id)->first();
        $bybitCredential = BybitCredential::where('user_id', $user->id)->first();
        $okxCredential = OKXCredential::where('user_id', $user->id)->first();

        return Inertia::render('Settings/Exchanges', [
            'binance' => $binanceCredential ? [
                'has_credentials' => true,
                'is_testnet' => $binanceCredential->is_testnet,
                'is_active' => $binanceCredential->is_active,
                'last_used_at' => $binanceCredential->last_used_at?->toISOString(),
                'last_error' => $binanceCredential->last_error,
            ] : ['has_credentials' => false],
            'bybit' => $bybitCredential ? [
                'has_credentials' => true,
                'is_testnet' => $bybitCredential->is_testnet,
                'is_active' => $bybitCredential->is_active,
                'last_used_at' => $bybitCredential->last_used_at?->toISOString(),
                'last_error' => $bybitCredential->last_error,
            ] : ['has_credentials' => false],
            'okx' => $okxCredential ? [
                'has_credentials' => true,
                'is_testnet' => $okxCredential->is_testnet,
                'is_active' => $okxCredential->is_active,
                'last_used_at' => $okxCredential->last_used_at?->toISOString(),
                'last_error' => $okxCredential->last_error,
            ] : ['has_credentials' => false],
        ]);
    }

    /**
     * Store Binance API credentials.
     */
    public function storeBinance(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'testnet' => 'boolean',
        ]);

        try {
            $credentials = BinanceCredential::firstOrNew(['user_id' => Auth::id()]);
            
            // Asignar valores usando los mutators
            $credentials->api_key = $request->api_key;
            $credentials->secret_key = $request->secret_key;
            $credentials->is_testnet = $request->boolean('testnet', false);
            $credentials->is_active = true;
            $credentials->save();

            // Test connection
            $service = new BinanceP2PService($credentials);
            $isConnected = $service->testConnection();

            if (!$isConnected) {
                $credentials->is_active = false;
                $credentials->last_error = 'Connection test failed';
                $credentials->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Las credenciales no son válidas o hay un problema de conexión.'
                ], 400);
            }

            $credentials->last_used_at = now();
            $credentials->last_error = null;
            $credentials->save();

            return response()->json([
                'success' => true,
                'message' => 'Credenciales guardadas y verificadas exitosamente!',
                'data' => [
                    'is_testnet' => $credentials->is_testnet,
                    'is_active' => $credentials->is_active,
                    'last_used_at' => $credentials->last_used_at?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error storing Binance credentials', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar las credenciales.'
            ], 500);
        }
    }

    /**
     * Store Bybit API credentials.
     */
    public function storeBybit(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'testnet' => 'boolean',
        ]);

        try {
            $credentials = BybitCredential::firstOrNew(['user_id' => Auth::id()]);
            
            // Asignar valores usando los mutators
            $credentials->api_key = $request->api_key;
            $credentials->secret_key = $request->secret_key;
            $credentials->is_testnet = $request->boolean('testnet', false);
            $credentials->is_active = true;
            $credentials->save();

            // Test connection
            $service = new BybitService($credentials);
            $isConnected = $service->testConnection();

            if (!$isConnected) {
                $credentials->is_active = false;
                $credentials->last_error = 'Connection test failed';
                $credentials->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Las credenciales no son válidas o hay un problema de conexión.'
                ], 400);
            }

            $credentials->last_used_at = now();
            $credentials->last_error = null;
            $credentials->save();

            return response()->json([
                'success' => true,
                'message' => 'Credenciales guardadas y verificadas exitosamente!',
                'data' => [
                    'is_testnet' => $credentials->is_testnet,
                    'is_active' => $credentials->is_active,
                    'last_used_at' => $credentials->last_used_at?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error storing Bybit credentials', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar las credenciales.'
            ], 500);
        }
    }

    /**
     * Store OKX API credentials.
     */
    public function storeOKX(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'passphrase' => 'required|string',
            'testnet' => 'boolean',
        ]);

        try {
            $credentials = OKXCredential::firstOrNew(['user_id' => Auth::id()]);
            
            // Asignar valores usando los mutators
            $credentials->api_key = $request->api_key;
            $credentials->secret_key = $request->secret_key;
            $credentials->passphrase = $request->passphrase;
            $credentials->is_testnet = $request->boolean('testnet', false);
            $credentials->is_active = true;
            $credentials->save();

            // Test connection
            $service = new OKXService($credentials);
            $isConnected = $service->testConnection();

            if (!$isConnected) {
                $credentials->is_active = false;
                $credentials->last_error = 'Connection test failed';
                $credentials->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Las credenciales no son válidas o hay un problema de conexión.'
                ], 400);
            }

            $credentials->last_used_at = now();
            $credentials->last_error = null;
            $credentials->save();

            return response()->json([
                'success' => true,
                'message' => 'Credenciales guardadas y verificadas exitosamente!',
                'data' => [
                    'is_testnet' => $credentials->is_testnet,
                    'is_active' => $credentials->is_active,
                    'last_used_at' => $credentials->last_used_at?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error storing OKX credentials', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar las credenciales.'
            ], 500);
        }
    }

    /**
     * Test connection for a specific exchange.
     */
    public function testConnection(Request $request, string $exchange)
    {
        $validationRules = [
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'testnet' => 'boolean',
        ];
        
        // OKX requiere passphrase
        if (strtolower($exchange) === 'okx') {
            $validationRules['passphrase'] = 'required|string';
        }
        
        $request->validate($validationRules);

        try {
            $isConnected = false;
            $directCredentials = [
                'api_key' => $request->api_key,
                'secret_key' => $request->secret_key,
                'is_testnet' => $request->boolean('testnet', false),
            ];
            
            // Agregar passphrase si es OKX
            if (strtolower($exchange) === 'okx') {
                $directCredentials['passphrase'] = $request->passphrase;
            }

            switch (strtolower($exchange)) {
                case 'binance':
                    // BinanceP2PService no acepta directCredentials, necesitamos crear un objeto temporal
                    $tempCredential = new BinanceCredential();
                    $tempCredential->api_key_encrypted = Crypt::encryptString($request->api_key);
                    $tempCredential->secret_key_encrypted = Crypt::encryptString($request->secret_key);
                    $tempCredential->is_testnet = $request->boolean('testnet', false);
                    // Los accessors funcionarán correctamente ahora
                    $service = new BinanceP2PService($tempCredential);
                    $isConnected = $service->testConnection();
                    break;

                case 'bybit':
                    $service = new BybitService(null, $directCredentials);
                    $isConnected = $service->testConnection();
                    break;

                case 'okx':
                    $service = new OKXService(null, $directCredentials);
                    $isConnected = $service->testConnection();
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Exchange no válido.'
                    ], 400);
            }

            if ($isConnected) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo establecer la conexión. Verifica tus credenciales.'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error testing connection', [
                'exchange' => $exchange,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al probar la conexión.'
            ], 500);
        }
    }

    /**
     * Delete credentials for a specific exchange.
     */
    public function deleteCredentials(string $exchange)
    {
        try {
            switch (strtolower($exchange)) {
                case 'binance':
                    $credential = BinanceCredential::where('user_id', Auth::id())->first();
                    break;

                case 'bybit':
                    $credential = BybitCredential::where('user_id', Auth::id())->first();
                    break;

                case 'okx':
                    $credential = OKXCredential::where('user_id', Auth::id())->first();
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Exchange no válido.'
                    ], 400);
            }

            if ($credential) {
                $credential->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Credenciales eliminadas exitosamente!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron credenciales para eliminar.'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error deleting credentials', [
                'exchange' => $exchange,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar las credenciales.'
            ], 500);
        }
    }
}

