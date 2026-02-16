<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('archived_events', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->uuid('aggregate_uuid');
            $table->unsignedInteger('aggregate_version')->nullable();
            $table->unsignedInteger('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->index('source_table');
            $table->index('aggregate_uuid');
            $table->index('original_created_at');
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_events');
    }
};
