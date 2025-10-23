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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // Identificadores únicos de Binance
            $table->string('order_number', 100)->unique();
            $table->string('advertisement_order_number', 100)->nullable();
            $table->string('trade_id', 100)->nullable(); // Para trades spot
            $table->string('transaction_id', 100)->nullable(); // Para otros tipos
            
            // Tipo de transacción y clasificación
            $table->enum('transaction_type', [
                'spot_trade',
                'p2p_order', 
                'deposit',
                'withdrawal',
                'pay_transaction',
                'c2c_order',
                'manual_entry'
            ]);
            
            $table->enum('order_type', ['BUY', 'SELL'])->nullable();
            
            // Activos y monedas
            $table->string('asset_type', 20);
            $table->string('fiat_type', 20)->nullable();
            
            // Montos y precios
            $table->decimal('total_price', 20, 8)->nullable();
            $table->decimal('price', 20, 8)->nullable();
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('amount', 20, 8)->nullable(); // Monto en fiat
            
            // Comisiones y fees
            $table->decimal('taker_fee', 10, 8)->default(0);
            $table->decimal('taker_fee_rate', 10, 8)->default(0);
            $table->decimal('commission', 10, 8)->default(0);
            $table->decimal('network_fee', 10, 8)->default(0);
            
            // Información de pago (P2P)
            $table->string('payment_method', 50)->nullable();
            $table->string('account_number', 255)->nullable();
            $table->string('counter_party', 255)->nullable();
            $table->string('counter_party_dni', 255)->nullable();
            $table->string('dni_type', 50)->nullable();
            $table->unsignedBigInteger('my_payment_method_id')->nullable();
            
            // Estado y fechas
            $table->enum('status', [
                'pending',
                'processing', 
                'completed',
                'cancelled',
                'failed',
                'expired'
            ]);
            
            $table->timestamp('binance_create_time')->nullable();
            $table->timestamp('binance_update_time')->nullable();
            
            // Información adicional
            $table->json('metadata')->nullable(); // Datos adicionales específicos del endpoint
            $table->text('notes')->nullable(); // Notas manuales
            $table->string('source_endpoint', 100)->nullable(); // Endpoint de origen
            
            // Campos de auditoría
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_manual_entry')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['transaction_type', 'status']);
            $table->index(['asset_type', 'fiat_type']);
            $table->index(['binance_create_time']);
            $table->index(['user_id']);
            $table->index(['order_number']);
            $table->index(['trade_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
