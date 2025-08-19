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
        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('severity', ['critical', 'error', 'warning', 'info']);
            $table->text('message');
            $table->json('context')->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index('severity');
            $table->index('acknowledged');
            $table->index('created_at');
            $table->index(['severity', 'acknowledged']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_alerts');
    }
};
