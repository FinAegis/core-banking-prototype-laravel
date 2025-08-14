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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('code', 50)->change();
        });

        // Also update the foreign key reference in basket_components
        Schema::table('basket_components', function (Blueprint $table) {
            $table->string('asset_code', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('basket_components', function (Blueprint $table) {
            $table->string('asset_code', 10)->change();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });
    }
};
