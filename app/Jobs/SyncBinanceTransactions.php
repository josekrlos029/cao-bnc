<?php

namespace App\Jobs;

use App\Models\BinanceCredential;
use App\Services\BinanceTransactionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncBinanceTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutos
    public int $tries = 3;

    private ?Carbon $startTime;
    private ?Carbon $endTime;
    private ?int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(Carbon $startTime = null, Carbon $endTime = null, int $userId = null)
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting Binance Transaction Sync Job', [
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'user_id' => $this->userId
            ]);

            // Obtener credenciales activas
            $credentialsQuery = BinanceCredential::active();
            
            if ($this->userId) {
                $credentialsQuery->where('user_id', $this->userId);
            }

            $credentials = $credentialsQuery->get();

            if ($credentials->isEmpty()) {
                Log::warning('No active Binance credentials found for sync');
                return;
            }

            $totalSynced = 0;
            $errors = [];

            foreach ($credentials as $credential) {
                try {
                    $syncService = new BinanceTransactionSyncService($credential);
                    
                    $results = $syncService->syncAllTransactions($this->startTime, $this->endTime);
                    
                    $synced = array_sum($results);
                    $totalSynced += $synced;

                    Log::info('Binance Transaction Sync Completed for User', [
                        'user_id' => $credential->user_id,
                        'synced_transactions' => $synced,
                        'breakdown' => $results
                    ]);

                    // Actualizar última sincronización
                    $credential->update(['last_used_at' => now()]);

                } catch (\Exception $e) {
                    $error = [
                        'user_id' => $credential->user_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ];
                    
                    $errors[] = $error;
                    
                    Log::error('Binance Transaction Sync Failed for User', $error);
                    
                    // Actualizar error en credenciales
                    $credential->update(['last_error' => $e->getMessage()]);
                }
            }

            Log::info('Binance Transaction Sync Job Completed', [
                'total_synced' => $totalSynced,
                'errors_count' => count($errors),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Binance Transaction Sync Job Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Binance Transaction Sync Job Failed Permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'user_id' => $this->userId
        ]);
    }
}
