<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('basket_assets', function (Blueprint $table) {
            $table->string('code', 50)->change();
        });

        Schema::table('basket_values', function (Blueprint $table) {
            $table->string('basket_asset_code', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('basket_assets', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });

        Schema::table('basket_values', function (Blueprint $table) {
            $table->string('basket_asset_code', 20)->change();
        });
    }
};
