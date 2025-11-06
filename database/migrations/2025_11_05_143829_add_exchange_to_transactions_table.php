<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar si la columna ya existe antes de agregarla
        if (!Schema::hasColumn('transactions', 'exchange')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('exchange', 50)->default('binance')->after('transaction_type');
            });
        }
        
        // Agregar índice si no existe
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('exchange');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['exchange']);
            $table->dropColumn('exchange');
        });
    }
};
