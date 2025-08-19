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
        Schema::create('monitoring_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->integer('aggregate_version')->unsigned();
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data');
            $table->timestamp('created_at', 6);

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index('event_class');
            $table->index('created_at');
        });

        Schema::create('monitoring_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->unique();
            $table->integer('aggregate_version')->unsigned();
            $table->json('state');
            $table->timestamps();

            $table->index(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_snapshots');
        Schema::dropIfExists('monitoring_events');
    }
};
