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
        Schema::create('bot_actions_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_configuration_id')->constrained()->onDelete('cascade');
            
            // Información de la acción
            $table->enum('action_type', ['suggest', 'execute', 'monitor', 'approve', 'reject']);
            $table->json('action_data'); // Datos específicos de la acción
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'executed', 'failed'])->default('pending_approval');
            $table->text('result')->nullable(); // Resultado de la ejecución
            $table->text('error_message')->nullable(); // Mensaje de error si falló
            
            // Metadatos
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            
            $table->index(['bot_configuration_id', 'action_type', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_actions_log');
    }
};
