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
        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->id();
            
            // Información del anuncio
            $table->string('ad_number')->unique();
            $table->string('fiat', 10);
            $table->string('asset', 10);
            $table->decimal('price', 15, 2);
            $table->decimal('available_amount', 15, 8);
            $table->decimal('min_limit', 15, 2);
            $table->decimal('max_limit', 15, 2);
            $table->json('payment_methods'); // Métodos de pago disponibles
            
            // Información del anunciante
            $table->string('advertiser_id')->nullable();
            $table->string('advertiser_nickname')->nullable();
            $table->integer('advertiser_month_finish_rate')->nullable();
            $table->integer('advertiser_month_order_count')->nullable();
            $table->integer('advertiser_month_finish_count')->nullable();
            
            // Estado y posición
            $table->enum('status', ['active', 'inactive', 'deleted'])->default('active');
            $table->integer('position')->nullable(); // Posición en el ranking
            $table->decimal('usd_difference', 15, 2)->nullable(); // Diferencia con precio USD
            
            // Metadatos
            $table->timestamp('binance_updated_at')->nullable();
            $table->timestamps();
            
            $table->index(['fiat', 'asset', 'status']);
            $table->index(['ad_number', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p2p_ads');
    }
};
