<?php

namespace App\Console\Commands;

use App\Models\BotConfiguration;
use App\Jobs\ProcessBotStrategy;
use Illuminate\Console\Command;

class BotStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:start {config_id? : ID de la configuración del bot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Iniciar el bot P2P con la configuración especificada';

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

            $config->is_active = true;
            $config->save();

            $this->info("Bot iniciado para la configuración: {$config->asset}/{$config->fiat}");
        } else {
            // Activar todas las configuraciones
            $count = BotConfiguration::where('is_active', false)->update(['is_active' => true]);
            $this->info("Se activaron {$count} configuraciones de bot");
        }

        // Disparar job de procesamiento
        ProcessBotStrategy::dispatch();

        $this->info('Job de procesamiento de estrategia disparado');
        return 0;
    }
}