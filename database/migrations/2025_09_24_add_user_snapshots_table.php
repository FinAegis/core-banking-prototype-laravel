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
        // User snapshots table for event sourcing
        Schema::create('user_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_uuid');
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamp('created_at');

            $table->index('aggregate_uuid');
            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_snapshots');
    }
};
