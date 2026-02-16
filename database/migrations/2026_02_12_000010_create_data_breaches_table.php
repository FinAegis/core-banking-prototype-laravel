<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title');
            $table->text('description');
            $table->timestamp('discovery_time')->nullable();
            $table->timestamp('notification_deadline')->nullable();
            $table->string('severity')->index();
            $table->string('status')->default('detected')->index();
            $table->json('affected_data_types')->nullable();
            $table->integer('affected_individuals_count')->default(0);
            $table->json('measures_taken')->nullable();
            $table->timestamp('authority_notified_at')->nullable();
            $table->timestamp('subjects_notified_at')->nullable();
            $table->string('reported_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('notification_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
