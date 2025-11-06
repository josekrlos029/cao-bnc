<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\BinanceCredential;
use App\Models\BybitCredential;
use App\Services\BinanceTransactionSyncService;
use App\Services\BybitTransactionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EnrichTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // 2 minutos
    public int $tries = 3;

    private int $transactionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $transactionId)
    {
        $this->transactionId = $transactionId;
        // Usar queue de prioridad baja para enriquecimiento
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Obtener la transacción
            $transaction = Transaction::find($this->transactionId);

            if (!$transaction) {
                Log::warning('EnrichTransaction: Transaction not found', [
                    'transaction_id' => $this->transactionId
                ]);
                return;
            }

            // Verificar que la transacción sea P2P y necesite enriquecimiento
            if ($transaction->transaction_type !== 'p2p_order') {
                Log::debug('EnrichTransaction: Transaction is not P2P, skipping enrichment', [
                    'transaction_id' => $this->transactionId,
                    'transaction_type' => $transaction->transaction_type
                ]);
                return;
            }

            // Actualizar estado a processing
            $this->updateEnrichmentStatus($transaction, 'processing');

            // Obtener credenciales del usuario según el exchange
            $credentials = null;
            $syncService = null;

            if ($transaction->exchange === 'binance') {
                $credentials = BinanceCredential::where('user_id', $transaction->user_id)
                    ->where('is_active', true)
                    ->first();

                if (!$credentials) {
                    Log::warning('EnrichTransaction: No active Binance credentials found', [
                        'transaction_id' => $this->transactionId,
                        'user_id' => $transaction->user_id
                    ]);
                    $this->updateEnrichmentStatus($transaction, 'failed');
                    return;
                }

                $syncService = new BinanceTransactionSyncService($credentials);
            } elseif ($transaction->exchange === 'bybit') {
                $credentials = BybitCredential::where('user_id', $transaction->user_id)
                    ->where('is_active', true)
                    ->first();

                if (!$credentials) {
                    Log::warning('EnrichTransaction: No active Bybit credentials found', [
                        'transaction_id' => $this->transactionId,
                        'user_id' => $transaction->user_id
                    ]);
                    $this->updateEnrichmentStatus($transaction, 'failed');
                    return;
                }

                $syncService = new BybitTransactionSyncService($credentials);
            } else {
                Log::warning('EnrichTransaction: Unknown exchange', [
                    'transaction_id' => $this->transactionId,
                    'exchange' => $transaction->exchange
                ]);
                $this->updateEnrichmentStatus($transaction, 'failed');
                return;
            }

            // Realizar enriquecimiento según el exchange
            if ($transaction->exchange === 'binance' && $syncService instanceof BinanceTransactionSyncService) {
                $this->enrichBinanceTransaction($transaction, $syncService);
            } elseif ($transaction->exchange === 'bybit' && $syncService instanceof BybitTransactionSyncService) {
                $this->enrichBybitTransaction($transaction, $syncService);
            }

        } catch (\Exception $e) {
            Log::error('EnrichTransaction: Error enriching transaction', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Actualizar estado a failed
            $transaction = Transaction::find($this->transactionId);
            if ($transaction) {
                $this->updateEnrichmentStatus($transaction, 'failed');
            }

            throw $e;
        }
    }

    /**
     * Enriquecer transacción de Binance
     */
    private function enrichBinanceTransaction(Transaction $transaction, BinanceTransactionSyncService $syncService): void
    {
        // Obtener advertisement_order_number para el enriquecimiento
        $adOrderNo = $transaction->order_number ?? $transaction->advertisement_order_number;

        if (!$adOrderNo) {
            Log::warning('EnrichTransaction: Missing advertisement_order_number for Binance transaction', [
                'transaction_id' => $this->transactionId,
                'order_number' => $transaction->order_number
            ]);
            $this->updateEnrichmentStatus($transaction, 'failed');
            return;
        }

        // Llamar al método de enriquecimiento
        try {
            $syncService->enrichP2POrderWithDetail($transaction, $adOrderNo, $transaction->order_type);
            $this->updateEnrichmentStatus($transaction, 'completed');

            Log::info('EnrichTransaction: Binance transaction enriched successfully', [
                'transaction_id' => $this->transactionId,
                'order_number' => $transaction->order_number
            ]);
        } catch (\Exception $e) {
            Log::error('EnrichTransaction: Error enriching Binance transaction', [
                'transaction_id' => $this->transactionId,
                'order_number' => $transaction->order_number,
                'error' => $e->getMessage()
            ]);
            $this->updateEnrichmentStatus($transaction, 'failed');
            throw $e;
        }
    }

    /**
     * Enriquecer transacción de Bybit
     */
    private function enrichBybitTransaction(Transaction $transaction, BybitTransactionSyncService $syncService): void
    {
        // Para Bybit, el enriquecimiento refresca los detalles de la orden
        try {
            $syncService->enrichP2POrderWithDetail($transaction);
            $this->updateEnrichmentStatus($transaction, 'completed');

            Log::info('EnrichTransaction: Bybit transaction enriched successfully', [
                'transaction_id' => $this->transactionId,
                'order_number' => $transaction->order_number
            ]);
        } catch (\Exception $e) {
            Log::error('EnrichTransaction: Error enriching Bybit transaction', [
                'transaction_id' => $this->transactionId,
                'order_number' => $transaction->order_number,
                'error' => $e->getMessage()
            ]);
            $this->updateEnrichmentStatus($transaction, 'failed');
            throw $e;
        }
    }

    /**
     * Actualizar estado de enriquecimiento
     */
    private function updateEnrichmentStatus(Transaction $transaction, string $status): void
    {
        // Solo actualizar si el campo existe (para compatibilidad con instalaciones sin migración)
        if (Schema::hasColumn('transactions', 'enrichment_status')) {
            $transaction->update(['enrichment_status' => $status]);
        }
    }
}

