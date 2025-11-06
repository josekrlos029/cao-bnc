<?php

namespace App\Console\Commands;

use App\Jobs\SyncBinanceTransactions;
use App\Jobs\SyncBybitTransactions;
use App\Jobs\SyncOKXTransactions;
use App\Models\BinanceCredential;
use App\Models\BybitCredential;
use App\Models\OKXCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncRecentTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:sync-recent {--minutes=10 : Number of minutes to sync back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar transacciones de los últimos N minutos para todos los usuarios y exchanges';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $startTime = now()->subMinutes($minutes);
        $endTime = now();

        $this->info("Iniciando sincronización de transacciones recientes...");
        $this->info("Período: {$startTime->format('Y-m-d H:i:s')} a {$endTime->format('Y-m-d H:i:s')}");
        $this->info("Rango: últimos {$minutes} minutos");
        $this->newLine();

        Log::info('Starting Recent Transactions Sync', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'minutes' => $minutes
        ]);

        // Obtener todos los usuarios únicos con credenciales activas de cualquier exchange
        $binanceUserIds = BinanceCredential::where('is_active', true)
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        $bybitUserIds = BybitCredential::where('is_active', true)
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        $okxUserIds = OKXCredential::where('is_active', true)
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        // Combinar y obtener usuarios únicos
        $allUserIds = array_unique(array_merge($binanceUserIds, $bybitUserIds, $okxUserIds));

        if (empty($allUserIds)) {
            $this->warn('No se encontraron usuarios con credenciales activas.');
            Log::warning('No active credentials found for any exchange');
            return 0;
        }

        $this->info("Usuarios encontrados: " . count($allUserIds));
        $this->newLine();

        // Estadísticas de sincronización
        $stats = [
            'binance' => ['users' => 0, 'synced' => 0, 'errors' => 0],
            'bybit' => ['users' => 0, 'synced' => 0, 'errors' => 0],
            'okx' => ['users' => 0, 'synced' => 0, 'errors' => 0],
        ];

        $progressBar = $this->output->createProgressBar(count($allUserIds));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Iniciando...');
        $progressBar->start();

        foreach ($allUserIds as $userId) {
            $progressBar->setMessage("Usuario ID: {$userId}");

            // Sincronizar Binance si el usuario tiene credenciales activas
            if (in_array($userId, $binanceUserIds)) {
                try {
                    $stats['binance']['users']++;
                    $this->syncForUser('binance', $userId, $startTime, $endTime);
                    $stats['binance']['synced']++;
                } catch (\Exception $e) {
                    $stats['binance']['errors']++;
                    Log::error('Error syncing Binance for user', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Sincronizar Bybit si el usuario tiene credenciales activas
            if (in_array($userId, $bybitUserIds)) {
                try {
                    $stats['bybit']['users']++;
                    $this->syncForUser('bybit', $userId, $startTime, $endTime);
                    $stats['bybit']['synced']++;
                } catch (\Exception $e) {
                    $stats['bybit']['errors']++;
                    Log::error('Error syncing Bybit for user', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Sincronizar OKX si el usuario tiene credenciales activas
            if (in_array($userId, $okxUserIds)) {
                try {
                    $stats['okx']['users']++;
                    $this->syncForUser('okx', $userId, $startTime, $endTime);
                    $stats['okx']['synced']++;
                } catch (\Exception $e) {
                    $stats['okx']['errors']++;
                    Log::error('Error syncing OKX for user', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->info('=== Resumen de Sincronización ===');
        $this->newLine();

        foreach ($stats as $exchange => $exchangeStats) {
            if ($exchangeStats['users'] > 0) {
                $this->line("{$exchange}:");
                $this->line("  - Usuarios procesados: {$exchangeStats['users']}");
                $this->line("  - Sincronizaciones exitosas: {$exchangeStats['synced']}");
                if ($exchangeStats['errors'] > 0) {
                    $this->error("  - Errores: {$exchangeStats['errors']}");
                }
                $this->newLine();
            }
        }

        $totalUsers = count($allUserIds);
        $totalSynced = $stats['binance']['synced'] + $stats['bybit']['synced'] + $stats['okx']['synced'];
        $totalErrors = $stats['binance']['errors'] + $stats['bybit']['errors'] + $stats['okx']['errors'];

        $this->info("Total usuarios procesados: {$totalUsers}");
        $this->info("Total sincronizaciones exitosas: {$totalSynced}");
        if ($totalErrors > 0) {
            $this->warn("Total errores: {$totalErrors}");
        }

        Log::info('Recent Transactions Sync Completed', [
            'total_users' => $totalUsers,
            'total_synced' => $totalSynced,
            'total_errors' => $totalErrors,
            'stats' => $stats,
            'start_time' => $startTime,
            'end_time' => $endTime
        ]);

        return 0;
    }

    /**
     * Sincronizar transacciones para un usuario y exchange específico
     */
    private function syncForUser(string $exchange, int $userId, Carbon $startTime, Carbon $endTime): void
    {
        try {
            switch (strtolower($exchange)) {
                case 'binance':
                    SyncBinanceTransactions::dispatchSync($startTime, $endTime, $userId);
                    break;
                case 'bybit':
                    SyncBybitTransactions::dispatchSync($startTime, $endTime, $userId);
                    break;
                case 'okx':
                    SyncOKXTransactions::dispatchSync($startTime, $endTime, $userId);
                    break;
                default:
                    throw new \InvalidArgumentException("Exchange desconocido: {$exchange}");
            }
        } catch (\Exception $e) {
            Log::error("Error in syncForUser for {$exchange}", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

