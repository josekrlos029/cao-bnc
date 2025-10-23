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
        Schema::create('market_data', function (Blueprint $table) {
            $table->id();
            
            // Datos de mercado
            $table->string('asset', 10);
            $table->string('fiat', 10);
            $table->decimal('price', 15, 2);
            $table->string('source')->default('binance_reference'); // binance_reference, binance_p2p, etc.
            
            // Metadatos adicionales
            $table->json('metadata')->nullable(); // Datos adicionales como volumen, cambios, etc.
            $table->timestamp('data_timestamp')->nullable(); // Timestamp de cuando se obtuvo el dato
            
            $table->timestamps();
            
            $table->index(['asset', 'fiat', 'source']);
            $table->index(['data_timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_data');
    }
};
