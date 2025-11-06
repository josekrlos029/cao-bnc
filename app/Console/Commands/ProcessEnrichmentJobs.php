<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessEnrichmentJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enrichment:process {--stop-when-empty : Stop when the queue is empty}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesar jobs de enriquecimiento pendientes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Procesando jobs de enriquecimiento...');
        $this->info('Usa Ctrl+C para detener el worker.');
        $this->newLine();
        
        $options = [
            'connection' => 'database',
            '--queue' => 'low',
            '--sleep' => 3,
            '--tries' => 3,
            '--max-time' => 3600,
        ];
        
        if ($this->option('stop-when-empty')) {
            $options['--stop-when-empty'] = true;
        }
        
        // Ejecutar el queue worker para procesar jobs de enriquecimiento
        $this->call('queue:work', $options);
        
        return 0;
    }
}
