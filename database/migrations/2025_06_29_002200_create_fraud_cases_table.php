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
        Schema::create('fraud_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('case_number')->unique();
            $table->string('type'); // transaction, account_takeover, money_mule, etc
            $table->enum('status', ['pending', 'investigating', 'confirmed', 'false_positive', 'resolved']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->json('detection_rules')->nullable(); // Which rules triggered
            $table->decimal('risk_score', 5, 2);
            $table->decimal('amount', 20, 8)->nullable();
            $table->string('currency', 3)->nullable();
            $table->uuid('subject_account_uuid')->nullable();
            $table->json('related_transactions')->nullable(); // Array of transaction IDs
            $table->json('related_accounts')->nullable(); // Array of account UUIDs
            $table->text('description')->nullable();
            $table->json('evidence')->nullable(); // Supporting evidence/data
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('assigned_to')->nullable(); // Analyst assigned
            $table->json('actions_taken')->nullable(); // Log of actions
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            $table->index('case_number');
            $table->index('type');
            $table->index('status');
            $table->index('severity');
            $table->index('subject_account_uuid');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_cases');
    }
};