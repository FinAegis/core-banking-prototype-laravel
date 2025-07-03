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
        Schema::create('system_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_incident_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['identified', 'in_progress', 'resolved']);
            $table->text('message');
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_incident_updates');
    }
};