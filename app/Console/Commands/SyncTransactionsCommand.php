<?php

namespace App\Console\Commands;

use App\Jobs\SyncBinanceTransactions;
use App\Models\BinanceCredential;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SyncTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'binance:sync-transactions 
                            {--user= : User ID to sync transactions for}
                            {--days=7 : Number of days to sync back}
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--queue : Run sync in background queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Binance transactions from multiple endpoints';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Binance Transaction Synchronization...');

        // Determinar fechas
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $this->info("Sync period: {$startTime->format('Y-m-d H:i:s')} to {$endTime->format('Y-m-d H:i:s')}");

        if ($userId) {
            $this->info("Syncing for user ID: {$userId}");
        }

        // Verificar credenciales
        $credentialsQuery = BinanceCredential::active();
        if ($userId) {
            $credentialsQuery->where('user_id', $userId);
        }

        $credentialsCount = $credentialsQuery->count();

        if ($credentialsCount === 0) {
            $this->error('No active Binance credentials found.');
            return 1;
        }

        $this->info("Found {$credentialsCount} active credential(s)");

        // Ejecutar sincronización
        if ($this->option('queue')) {
            $this->info('Dispatching sync job to queue...');
            SyncBinanceTransactions::dispatch($startTime, $endTime, $userId);
            $this->info('Sync job dispatched successfully!');
        } else {
            $this->info('Running sync synchronously...');
            SyncBinanceTransactions::dispatchSync($startTime, $endTime, $userId);
            $this->info('Sync completed successfully!');
        }

        return 0;
    }

    private function getStartTime(): Carbon
    {
        if ($start = $this->option('start')) {
            return Carbon::createFromFormat('Y-m-d', $start)->startOfDay();
        }

        $days = (int) $this->option('days');
        return now()->subDays($days)->startOfDay();
    }

    private function getEndTime(): Carbon
    {
        if ($end = $this->option('end')) {
            return Carbon::createFromFormat('Y-m-d', $end)->endOfDay();
        }

        return now()->endOfDay();
    }
}
