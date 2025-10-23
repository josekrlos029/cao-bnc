<?php

namespace App\Console\Commands;

use App\Models\BotConfiguration;
use Illuminate\Console\Command;

class BotStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:stop {config_id? : ID de la configuración del bot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detener el bot P2P con la configuración especificada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $configId = $this->argument('config_id');

        if ($configId) {
            $config = BotConfiguration::find($configId);
            
            if (!$config) {
                $this->error("No se encontró la configuración con ID: {$configId}");
                return 1;
            }

            $config->is_active = false;
            $config->save();

            $this->info("Bot detenido para la configuración: {$config->asset}/{$config->fiat}");
        } else {
            // Desactivar todas las configuraciones
            $count = BotConfiguration::where('is_active', true)->update(['is_active' => false]);
            $this->info("Se desactivaron {$count} configuraciones de bot");
        }

        return 0;
    }
}