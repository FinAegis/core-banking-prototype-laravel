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
        Schema::table('stored_events', function (Blueprint $table) {
            $table->string('aggregate_uuid', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stored_events', function (Blueprint $table) {
            $table->uuid('aggregate_uuid')->nullable()->change();
        });
    }
};
