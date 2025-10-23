<?php

namespace App\Jobs;

use App\Models\BinanceCredential;
use App\Models\MarketData;
use App\Services\BinanceP2PService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorMarketData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $asset;
    private string $fiat;

    /**
     * Create a new job instance.
     */
    public function __construct(string $asset = 'BTC', string $fiat = 'COP')
    {
        $this->asset = $asset;
        $this->fiat = $fiat;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Obtener credenciales activas
            $credentials = BinanceCredential::where('is_active', true)->first();

            if (!$credentials) {
                Log::warning('No active Binance credentials found for market data monitoring');
                return;
            }

            $service = new BinanceP2PService($credentials);
            
            // Actualizar datos de mercado
            $success = $service->updateMarketData($this->asset, $this->fiat);

            if ($success) {
                Log::info('Market data updated successfully', [
                    'asset' => $this->asset,
                    'fiat' => $this->fiat
                ]);
            } else {
                Log::warning('Failed to update market data', [
                    'asset' => $this->asset,
                    'fiat' => $this->fiat
                ]);
            }

        } catch (\Exception $e) {
            Log::error('MonitorMarketData job failed', [
                'asset' => $this->asset,
                'fiat' => $this->fiat,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}