<?php

namespace App\Http\Controllers;

use App\Models\BotConfiguration;
use App\Models\BotActionLog;
use App\Services\BinanceP2PService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    /**
     * Display a listing of trading bots.
     */
    public function index()
    {
        return inertia('Bots/Index');
    }

    /**
     * Show the form for creating a new bot.
     */
    public function create()
    {
        return inertia('Bots/Create');
    }

    /**
     * Store a newly created bot.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'strategy' => 'required|string',
            'symbol' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        // TODO: Implement bot creation logic
        
        return redirect()->route('bots.index')->with('success', 'Bot created successfully!');
    }

    /**
     * Display the specified bot.
     */
    public function show($id)
    {
        return inertia('Bots/Show', ['botId' => $id]);
    }

    /**
     * Update the specified bot.
     */
    public function update(Request $request, $id)
    {
        // TODO: Implement bot update logic
        
        return redirect()->back()->with('success', 'Bot updated successfully!');
    }

    /**
     * Remove the specified bot.
     */
    public function destroy($id)
    {
        // TODO: Implement bot deletion logic
        
        return redirect()->route('bots.index')->with('success', 'Bot deleted successfully!');
    }

    /**
     * Start the specified bot.
     */
    public function start($id)
    {
        // TODO: Implement bot start logic
        
        return redirect()->back()->with('success', 'Bot started successfully!');
    }

    /**
     * Stop the specified bot.
     */
    public function stop($id)
    {
        // TODO: Implement bot stop logic
        
        return redirect()->back()->with('success', 'Bot stopped successfully!');
    }

    /**
     * Guardar configuración del bot P2P
     */
    public function saveConfiguration(Request $request)
    {
        $request->validate([
            'fiat' => 'required|string|max:10',
            'asset' => 'required|string|max:10',
            'asset_rate' => 'nullable|numeric',
            'operation' => 'required|in:BUY,SELL',
            'min_limit' => 'nullable|numeric|min:0',
            'max_limit' => 'nullable|numeric|min:0',
            'payment_methods' => 'nullable|array',
            'ad_number' => 'nullable|string',
            'min_positions' => 'required|integer|min:1|max:20',
            'max_positions' => 'required|integer|min:1|max:20',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_usd_diff' => 'nullable|numeric',
            'max_usd_diff' => 'nullable|numeric',
            'profile' => 'required|in:agresivo,moderado,conservador',
            'increment' => 'nullable|numeric',
            'difference' => 'nullable|numeric',
            'max_price_enabled' => 'boolean',
            'max_price_limit' => 'nullable|numeric|min:0',
            'min_volume_enabled' => 'boolean',
            'min_volume' => 'nullable|numeric|min:0',
            'min_limit_enabled' => 'boolean',
            'min_limit_threshold' => 'nullable|numeric|min:0',
        ]);

        try {
            $config = BotConfiguration::updateOrCreate(
                ['user_id' => Auth::id()],
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Configuración guardada exitosamente',
                'config' => $config
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving bot configuration', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la configuración'
            ], 500);
        }
    }

    /**
     * Obtener configuración actual del bot
     */
    public function getConfiguration()
    {
        try {
            $config = BotConfiguration::where('user_id', Auth::id())->first();
            
            return response()->json([
                'success' => true,
                'config' => $config
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting bot configuration', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la configuración'
            ], 500);
        }
    }

    /**
     * Activar/desactivar bot
     */
    public function toggleBotStatus($id)
    {
        try {
            $config = BotConfiguration::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->is_active = !$config->is_active;
            $config->save();

            return response()->json([
                'success' => true,
                'message' => $config->is_active ? 'Bot activado' : 'Bot desactivado',
                'is_active' => $config->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling bot status', [
                'bot_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del bot'
            ], 500);
        }
    }

    /**
     * Obtener acciones sugeridas pendientes
     */
    public function getActionsSuggested()
    {
        try {
            $config = BotConfiguration::where('user_id', Auth::id())->first();
            
            if (!$config) {
                return response()->json([
                    'success' => true,
                    'actions' => []
                ]);
            }

            $actions = BotActionLog::where('bot_configuration_id', $config->id)
                ->where('status', 'pending_approval')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'actions' => $actions
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting suggested actions', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las acciones sugeridas'
            ], 500);
        }
    }

    /**
     * Aprobar acción sugerida
     */
    public function approveAction($actionId)
    {
        try {
            $action = BotActionLog::where('id', $actionId)
                ->whereHas('botConfiguration', function($query) {
                    $query->where('user_id', Auth::id());
                })
                ->firstOrFail();

            $action->status = 'approved';
            $action->save();

            // TODO: Disparar job para ejecutar la acción aprobada

            return response()->json([
                'success' => true,
                'message' => 'Acción aprobada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error approving action', [
                'action_id' => $actionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar la acción'
            ], 500);
        }
    }

    /**
     * Rechazar acción sugerida
     */
    public function rejectAction($actionId)
    {
        try {
            $action = BotActionLog::where('id', $actionId)
                ->whereHas('botConfiguration', function($query) {
                    $query->where('user_id', Auth::id());
                })
                ->firstOrFail();

            $action->status = 'rejected';
            $action->save();

            return response()->json([
                'success' => true,
                'message' => 'Acción rechazada'
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting action', [
                'action_id' => $actionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar la acción'
            ], 500);
        }
    }

    /**
     * Obtener historial de acciones del bot
     */
    public function getActionHistory()
    {
        try {
            $config = BotConfiguration::where('user_id', Auth::id())->first();
            
            if (!$config) {
                return response()->json([
                    'success' => true,
                    'history' => []
                ]);
            }

            $history = BotActionLog::where('bot_configuration_id', $config->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting action history', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial'
            ], 500);
        }
    }
}
