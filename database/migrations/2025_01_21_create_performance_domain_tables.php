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
        // Performance metrics table
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_id');
            $table->string('system_id');
            $table->string('name');
            $table->decimal('value', 20, 6);
            $table->string('type', 50);
            $table->json('tags')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['name', 'recorded_at']);
            $table->index(['type', 'recorded_at']);
            $table->index(['system_id', 'recorded_at']);
            $table->index('metric_id');
            $table->index('recorded_at');
        });

        // Performance alerts table
        Schema::create('performance_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('metric_id');
            $table->string('system_id');
            $table->string('alert_type');
            $table->string('metric_name');
            $table->decimal('value', 20, 6);
            $table->decimal('threshold', 20, 6);
            $table->string('severity');
            $table->text('message');
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['metric_name', 'triggered_at']);
            $table->index(['severity', 'triggered_at']);
            $table->index('resolved_at');
        });

        // Performance reports table
        Schema::create('performance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_id');
            $table->string('system_id');
            $table->string('report_type');
            $table->json('report_data');
            $table->timestamp('from_date');
            $table->timestamp('to_date');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['report_type', 'generated_at']);
            $table->index(['system_id', 'generated_at']);
        });

        // Performance events table for event sourcing
        Schema::create('performance_events', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_uuid');
            $table->unsignedInteger('aggregate_version');
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data');
            $table->timestamp('created_at');

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index('aggregate_uuid');
            $table->index('event_class');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reports');
        Schema::dropIfExists('performance_alerts');
        Schema::dropIfExists('performance_metrics');
        Schema::dropIfExists('performance_events');
    }
};
