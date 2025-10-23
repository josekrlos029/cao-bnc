<?php

namespace App\Jobs;

use App\Models\BotActionLog;
use App\Models\BinanceCredential;
use App\Services\BinanceP2PService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteApprovedActions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $actionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $actionId)
    {
        $this->actionId = $actionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $action = BotActionLog::find($this->actionId);

            if (!$action) {
                Log::error('Action not found', ['action_id' => $this->actionId]);
                return;
            }

            if ($action->status !== 'approved') {
                Log::warning('Action is not approved', [
                    'action_id' => $this->actionId,
                    'status' => $action->status
                ]);
                return;
            }

            // Obtener credenciales del usuario
            $credentials = BinanceCredential::where('user_id', $action->botConfiguration->user_id)
                ->where('is_active', true)
                ->first();

            if (!$credentials) {
                $this->markActionAsFailed($action, 'No hay credenciales de Binance activas');
                return;
            }

            $service = new BinanceP2PService($credentials);
            $actionData = $action->action_data;

            // Ejecutar la acción según el tipo
            $result = $this->executeAction($service, $actionData, $action);

            if ($result['success']) {
                $this->markActionAsExecuted($action, $result['message']);
            } else {
                $this->markActionAsFailed($action, $result['message']);
            }

        } catch (\Exception $e) {
            Log::error('ExecuteApprovedActions job failed', [
                'action_id' => $this->actionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($action)) {
                $this->markActionAsFailed($action, $e->getMessage());
            }
        }
    }

    private function executeAction(BinanceP2PService $service, array $actionData, BotActionLog $action): array
    {
        switch ($actionData['action']) {
            case 'update_price':
                return $this->executePriceUpdate($service, $actionData, $action);
            
            case 'aggressive_price_cut':
            case 'moderate_price_adjustment':
            case 'conservative_price_adjustment':
                return $this->executePriceAdjustment($service, $actionData, $action);
            
            default:
                return [
                    'success' => false,
                    'message' => 'Tipo de acción no soportado: ' . $actionData['action']
                ];
        }
    }

    private function executePriceUpdate(BinanceP2PService $service, array $actionData, BotActionLog $action): array
    {
        try {
            $config = $action->botConfiguration;
            
            if (!$config->ad_number) {
                return [
                    'success' => false,
                    'message' => 'No hay número de anuncio configurado'
                ];
            }

            $updateData = [
                'price' => $actionData['suggested_price'],
                'minSingleTransAmount' => $config->min_limit,
                'maxSingleTransAmount' => $config->max_limit,
                'payMethods' => $this->formatPaymentMethods($config->payment_methods)
            ];

            $result = $service->updateAd($config->ad_number, $updateData);

            if (!empty($result)) {
                return [
                    'success' => true,
                    'message' => 'Precio actualizado exitosamente a ' . $actionData['suggested_price']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar el precio en Binance'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error ejecutando actualización de precio: ' . $e->getMessage()
            ];
        }
    }

    private function executePriceAdjustment(BinanceP2PService $service, array $actionData, BotActionLog $action): array
    {
        // Similar a executePriceUpdate pero con lógica específica para ajustes
        return $this->executePriceUpdate($service, $actionData, $action);
    }

    private function formatPaymentMethods(array $paymentMethods): array
    {
        $formatted = [];
        
        foreach ($paymentMethods as $method => $enabled) {
            if ($enabled) {
                $formatted[] = [
                    'payType' => strtoupper($method),
                    'payTypeId' => $this->getPaymentMethodId($method)
                ];
            }
        }

        return $formatted;
    }

    private function getPaymentMethodId(string $method): string
    {
        // Mapeo de métodos de pago a IDs de Binance
        $mapping = [
            'nequi' => 'Nequi',
            'bancolombia' => 'BancolombiaSA'
        ];

        return $mapping[$method] ?? strtoupper($method);
    }

    private function markActionAsExecuted(BotActionLog $action, string $message): void
    {
        $action->status = 'executed';
        $action->result = $message;
        $action->executed_at = now();
        $action->save();

        Log::info('Action executed successfully', [
            'action_id' => $action->id,
            'message' => $message
        ]);
    }

    private function markActionAsFailed(BotActionLog $action, string $errorMessage): void
    {
        $action->status = 'failed';
        $action->error_message = $errorMessage;
        $action->executed_at = now();
        $action->save();

        Log::error('Action execution failed', [
            'action_id' => $action->id,
            'error' => $errorMessage
        ]);
    }
}