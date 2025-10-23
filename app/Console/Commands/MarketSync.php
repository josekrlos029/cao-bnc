<?php

namespace App\Console\Commands;

use App\Jobs\MonitorMarketData;
use App\Jobs\SyncP2PAds;
use Illuminate\Console\Command;

class MarketSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:sync {--asset=BTC : Asset a sincronizar} {--fiat=COP : Fiat a sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar datos de mercado y anuncios P2P';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $asset = $this->option('asset');
        $fiat = $this->option('fiat');

        $this->info("Sincronizando datos de mercado para {$asset}/{$fiat}...");

        // Disparar jobs de sincronización
        MonitorMarketData::dispatch($asset, $fiat);
        SyncP2PAds::dispatch($asset, $fiat);

        $this->info('Jobs de sincronización disparados exitosamente');
        $this->info('- MonitorMarketData: Actualizando datos de mercado');
        $this->info('- SyncP2PAds: Sincronizando anuncios P2P');

        return 0;
    }
}