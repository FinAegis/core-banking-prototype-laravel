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
        // Drop foreign key constraints that reference assets.code
        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropForeign(['asset_code']);
        });
        
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropForeign(['from_asset_code']);
            $table->dropForeign(['to_asset_code']);
        });
        
        Schema::table('basket_components', function (Blueprint $table) {
            $table->dropForeign(['asset_code']);
        });
        
        // Increase code field length from 10 to 20 to accommodate basket codes
        Schema::table('assets', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });
        
        // Also update the foreign key columns to match
        Schema::table('account_balances', function (Blueprint $table) {
            $table->string('asset_code', 20)->change();
        });
        
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->string('from_asset_code', 20)->change();
            $table->string('to_asset_code', 20)->change();
        });
        
        Schema::table('basket_components', function (Blueprint $table) {
            $table->string('asset_code', 20)->change();
        });
        
        // Re-add foreign key constraints
        Schema::table('account_balances', function (Blueprint $table) {
            $table->foreign('asset_code')->references('code')->on('assets');
        });
        
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->foreign('from_asset_code')->references('code')->on('assets');
            $table->foreign('to_asset_code')->references('code')->on('assets');
        });
        
        Schema::table('basket_components', function (Blueprint $table) {
            $table->foreign('asset_code')->references('code')->on('assets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints
        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropForeign(['asset_code']);
        });
        
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropForeign(['from_asset_code']);
            $table->dropForeign(['to_asset_code']);
        });
        
        Schema::table('basket_components', function (Blueprint $table) {
            $table->dropForeign(['asset_code']);
        });
        
        // Revert to original length
        Schema::table('assets', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });
        
        Schema::table('account_balances', function (Blueprint $table) {
            $table->string('asset_code', 10)->change();
        });
        
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->string('from_asset_code', 10)->change();
            $table->string('to_asset_code', 10)->change();
        });
        
        Schema::table('basket_components', function (Blueprint $table) {
            $table->string('asset_code', 10)->change();
        });
        
        // Re-add foreign key constraints
        Schema::table('account_balances', function (Blueprint $table) {
            $table->foreign('asset_code')->references('code')->on('assets');
        });
        
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->foreign('from_asset_code')->references('code')->on('assets');
            $table->foreign('to_asset_code')->references('code')->on('assets');
        });
        
        Schema::table('basket_components', function (Blueprint $table) {
            $table->foreign('asset_code')->references('code')->on('assets');
        });
    }
};