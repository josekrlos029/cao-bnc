<?php

namespace App\Jobs;

use App\Models\BinanceCredential;
use App\Models\P2PAd;
use App\Services\BinanceP2PService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncP2PAds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $asset;
    private string $fiat;
    private int $limit;

    /**
     * Create a new job instance.
     */
    public function __construct(string $asset = 'BTC', string $fiat = 'COP', int $limit = 50)
    {
        $this->asset = $asset;
        $this->fiat = $fiat;
        $this->limit = $limit;
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
                Log::warning('No active Binance credentials found for P2P ads sync');
                return;
            }

            $service = new BinanceP2PService($credentials);
            
            // Sincronizar anuncios P2P
            $synced = $service->syncP2PAds($this->asset, $this->fiat, $this->limit);

            // Actualizar posiciones de los anuncios
            $this->updateAdPositions();

            Log::info('P2P ads synced successfully', [
                'asset' => $this->asset,
                'fiat' => $this->fiat,
                'synced_count' => $synced
            ]);

        } catch (\Exception $e) {
            Log::error('SyncP2PAds job failed', [
                'asset' => $this->asset,
                'fiat' => $this->fiat,
                'limit' => $this->limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function updateAdPositions(): void
    {
        try {
            // Obtener anuncios activos ordenados por precio
            $ads = P2PAd::where('asset', $this->asset)
                ->where('fiat', $this->fiat)
                ->where('status', 'active')
                ->orderBy('price', 'asc')
                ->get();

            // Actualizar posiciones
            foreach ($ads as $index => $ad) {
                $ad->position = $index + 1;
                $ad->save();
            }

            Log::info('Ad positions updated', [
                'asset' => $this->asset,
                'fiat' => $this->fiat,
                'updated_count' => $ads->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ad positions', [
                'asset' => $this->asset,
                'fiat' => $this->fiat,
                'error' => $e->getMessage()
            ]);
        }
    }
}