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
        Schema::create('trade_history', function (Blueprint $table) {
            $table->id();
            
            // Información del trade
            $table->string('order_id')->unique();
            $table->string('ad_number');
            $table->string('fiat', 10);
            $table->string('asset', 10);
            $table->decimal('amount', 15, 8);
            $table->decimal('price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->enum('trade_type', ['buy', 'sell']);
            $table->enum('status', ['pending', 'completed', 'cancelled', 'disputed'])->default('pending');
            
            // Información de las partes
            $table->string('buyer_id')->nullable();
            $table->string('buyer_nickname')->nullable();
            $table->string('seller_id')->nullable();
            $table->string('seller_nickname')->nullable();
            
            // Metadatos
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['ad_number', 'status']);
            $table->index(['trade_type', 'status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_history');
    }
};
