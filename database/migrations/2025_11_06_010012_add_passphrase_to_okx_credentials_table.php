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
        Schema::table('okx_credentials', function (Blueprint $table) {
            $table->text('passphrase_encrypted')->after('secret_key_encrypted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('okx_credentials', function (Blueprint $table) {
            $table->dropColumn('passphrase_encrypted');
        });
    }
};
