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
        Schema::create('collateral_position_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid');
            $table->unsignedBigInteger('aggregate_version');
            $table->jsonb('state');
            $table->timestamp('created_at');

            $table->index('aggregate_uuid');
            $table->unique(['aggregate_uuid', 'aggregate_version'], 'collateral_position_snapshots_aggregate_uuid_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_position_snapshots');
    }
};
