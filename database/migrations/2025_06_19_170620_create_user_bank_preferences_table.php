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
        Schema::create('user_bank_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid');
            $table->string('bank_code', 50);
            $table->string('bank_name', 100);
            $table->decimal('allocation_percentage', 5, 2);
            $table->boolean('is_primary')->default(false);
            $table->enum('status', ['active', 'pending', 'suspended'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('user_uuid');
            $table->index(['user_uuid', 'bank_code']);
            $table->index('status');
            
            // Foreign key
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            
            // Ensure allocations per user sum to 100%
            $table->unique(['user_uuid', 'bank_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_bank_preferences');
    }
};