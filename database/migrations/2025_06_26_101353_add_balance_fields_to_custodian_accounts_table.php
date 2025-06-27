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
        Schema::table('custodian_accounts', function (Blueprint $table) {
            $table->bigInteger('last_known_balance')->nullable()->after('metadata');
            $table->timestamp('last_synced_at')->nullable()->after('last_known_balance');
            
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodian_accounts', function (Blueprint $table) {
            $table->dropIndex(['last_synced_at']);
            $table->dropColumn(['last_known_balance', 'last_synced_at']);
        });
    }
};
