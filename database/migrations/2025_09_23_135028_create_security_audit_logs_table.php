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
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('agent_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['agent_id', 'occurred_at']);
            $table->index(['transaction_id', 'occurred_at']);
            $table->index(['event_type', 'severity', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
