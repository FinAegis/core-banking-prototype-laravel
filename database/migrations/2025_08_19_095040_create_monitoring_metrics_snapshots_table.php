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
        Schema::create('monitoring_metrics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->json('metrics');
            $table->json('health_status')->nullable();
            $table->integer('metrics_count')->default(0);
            $table->enum('overall_health', ['healthy', 'degraded', 'unhealthy', 'unknown'])->default('unknown');
            $table->timestamp('created_at')->useCurrent();

            $table->index('overall_health');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_metrics_snapshots');
    }
};
