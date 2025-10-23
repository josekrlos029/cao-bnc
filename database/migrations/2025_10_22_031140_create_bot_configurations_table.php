<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Datos de anuncio
            $table->string('fiat', 10)->default('COP');
            $table->string('asset', 10)->default('BTC');
            $table->decimal('asset_rate', 15, 8)->nullable();
            $table->enum('operation', ['BUY', 'SELL'])->default('BUY');
            $table->decimal('min_limit', 15, 2)->nullable();
            $table->decimal('max_limit', 15, 2)->nullable();
            $table->json('payment_methods')->nullable(); // Nequi, Bancolombia, etc.
            $table->string('ad_number')->nullable();
            
            // Configuración de posiciones
            $table->integer('min_positions')->default(3);
            $table->integer('max_positions')->default(8);
            
            // Configuración de precios
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            
            // Diferencia USD
            $table->decimal('min_usd_diff', 15, 2)->nullable();
            $table->decimal('max_usd_diff', 15, 2)->nullable();
            
            // Perfil
            $table->enum('profile', ['agresivo', 'moderado', 'conservador'])->default('agresivo');
            
            // Ajuste de ascenso
            $table->decimal('increment', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            
            // Configuraciones adicionales
            $table->boolean('max_price_enabled')->default(false);
            $table->decimal('max_price_limit', 15, 2)->nullable();
            $table->boolean('min_volume_enabled')->default(false);
            $table->decimal('min_volume', 15, 2)->nullable();
            $table->boolean('min_limit_enabled')->default(false);
            $table->decimal('min_limit_threshold', 15, 2)->nullable();
            
            // Estado
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_configurations');
    }
};
