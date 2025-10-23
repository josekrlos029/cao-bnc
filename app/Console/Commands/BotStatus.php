<?php

namespace App\Console\Commands;

use App\Models\BotConfiguration;
use App\Models\BotActionLog;
use App\Models\MarketData;
use App\Models\P2PAd;
use Illuminate\Console\Command;

class BotStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mostrar el estado actual de los bots P2P';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Estado de los Bots P2P ===');
        $this->newLine();

        // Configuraciones activas
        $activeConfigs = BotConfiguration::where('is_active', true)->get();
        $inactiveConfigs = BotConfiguration::where('is_active', false)->get();

        $this->info("Configuraciones activas: {$activeConfigs->count()}");
        $this->info("Configuraciones inactivas: {$inactiveConfigs->count()}");
        $this->newLine();

        if ($activeConfigs->count() > 0) {
            $this->info('Configuraciones activas:');
            $this->table(
                ['ID', 'Asset/Fiat', 'Operación', 'Perfil', 'Última verificación'],
                $activeConfigs->map(function ($config) {
                    return [
                        $config->id,
                        "{$config->asset}/{$config->fiat}",
                        $config->operation,
                        $config->profile,
                        $config->last_checked_at ? $config->last_checked_at->format('Y-m-d H:i:s') : 'Nunca'
                    ];
                })
            );
        }

        // Acciones pendientes
        $pendingActions = BotActionLog::where('status', 'pending_approval')->count();
        $this->info("Acciones pendientes de aprobación: {$pendingActions}");

        // Datos de mercado
        $latestMarketData = MarketData::latest('data_timestamp')->first();
        if ($latestMarketData) {
            $this->info("Último dato de mercado: {$latestMarketData->asset}/{$latestMarketData->fiat} - {$latestMarketData->price} ({$latestMarketData->data_timestamp->format('Y-m-d H:i:s')})");
        }

        // Anuncios P2P
        $p2pAdsCount = P2PAd::where('status', 'active')->count();
        $this->info("Anuncios P2P activos: {$p2pAdsCount}");

        return 0;
    }
}