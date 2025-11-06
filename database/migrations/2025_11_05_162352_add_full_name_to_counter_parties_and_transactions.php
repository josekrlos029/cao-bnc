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
        // Agregar full_name a counter_parties
        if (Schema::hasTable('counter_parties')) {
            Schema::table('counter_parties', function (Blueprint $table) {
                $table->string('full_name', 255)->nullable()->after('counter_party');
            });
        }
        
        // Agregar full_name a transactions
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('counter_party_full_name', 255)->nullable()->after('counter_party');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar full_name de counter_parties
        if (Schema::hasTable('counter_parties')) {
            Schema::table('counter_parties', function (Blueprint $table) {
                $table->dropColumn('full_name');
            });
        }
        
        // Eliminar counter_party_full_name de transactions
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('counter_party_full_name');
            });
        }
    }
};
