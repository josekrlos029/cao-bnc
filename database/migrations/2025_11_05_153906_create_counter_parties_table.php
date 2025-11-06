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
        Schema::create('counter_parties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('exchange', 50)->default('binance');
            $table->string('counter_party', 255)->nullable(); // nombre/nickname
            $table->string('merchant_no', 255)->nullable(); // de enrich
            $table->string('counter_party_dni', 255)->nullable();
            $table->string('dni_type', 50)->nullable(); // CC, CE, PASSPORT, NIT, etc.
            $table->timestamps();
            
            // Índices
            $table->unique(['user_id', 'exchange', 'counter_party']);
            $table->index(['user_id', 'exchange', 'merchant_no']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_parties');
    }
};
