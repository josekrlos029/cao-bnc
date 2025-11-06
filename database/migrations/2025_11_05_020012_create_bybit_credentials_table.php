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
        Schema::create('bybit_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Credenciales encriptadas
            $table->text('api_key_encrypted');
            $table->text('secret_key_encrypted');
            
            // Configuración
            $table->boolean('is_testnet')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Metadatos
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            
            $table->unique('user_id'); // Un usuario solo puede tener una credencial
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bybit_credentials');
    }
};
