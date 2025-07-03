<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('service')->nullable();
            $table->enum('impact', ['minor', 'major', 'critical']);
            $table->enum('status', ['identified', 'in_progress', 'resolved']);
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('affected_services')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'started_at']);
            $table->index('service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_incidents');
    }
};