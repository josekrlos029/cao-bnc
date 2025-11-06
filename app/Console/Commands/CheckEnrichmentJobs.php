<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

class CheckEnrichmentJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enrichment:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar estado de jobs de enriquecimiento y transacciones pendientes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Estado de Enriquecimiento ===');
        $this->newLine();

        // Verificar jobs en la cola
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'low')
            ->count();

        $failedJobs = DB::table('failed_jobs')->count();

        $this->info("Jobs pendientes en cola 'low': {$pendingJobs}");
        $this->info("Jobs fallidos: {$failedJobs}");
        $this->newLine();

        // Verificar transacciones P2P y su estado de enriquecimiento
        $p2pTransactions = Transaction::where('transaction_type', 'p2p_order')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN enrichment_status IS NULL THEN 1 ELSE 0 END) as not_started,
                SUM(CASE WHEN enrichment_status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN enrichment_status = "processing" THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN enrichment_status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN enrichment_status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        $this->info('=== Estado de Transacciones P2P ===');
        $this->table(
            ['Estado', 'Cantidad'],
            [
                ['Total P2P', $p2pTransactions->total ?? 0],
                ['Sin iniciar', $p2pTransactions->not_started ?? 0],
                ['Pendiente', $p2pTransactions->pending ?? 0],
                ['Procesando', $p2pTransactions->processing ?? 0],
                ['Completadas', $p2pTransactions->completed ?? 0],
                ['Fallidas', $p2pTransactions->failed ?? 0],
            ]
        );

        $this->newLine();

        if ($pendingJobs > 0) {
            $this->warn("⚠️  Hay {$pendingJobs} jobs pendientes en la cola.");
            $this->info("Ejecuta 'php artisan enrichment:process' para procesarlos.");
        } else {
            $this->info("✓ No hay jobs pendientes en la cola.");
        }

        if ($p2pTransactions && ($p2pTransactions->pending > 0 || $p2pTransactions->processing > 0)) {
            $active = ($p2pTransactions->pending ?? 0) + ($p2pTransactions->processing ?? 0);
            $this->warn("⚠️  Hay {$active} transacciones esperando enriquecimiento.");
        }

        return 0;
    }
}
