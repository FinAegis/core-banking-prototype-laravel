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
        Schema::create('agent_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_payment_id')->nullable()->index();
            $table->string('transaction_id')->unique();
            $table->string('payment_id')->index();
            $table->string('from_agent_did')->index();
            $table->string('to_agent_did')->index();
            $table->decimal('amount', 20, 8);
            $table->string('currency', 10);
            $table->string('status')->index();
            $table->string('payment_type', 50);
            $table->decimal('fees', 20, 8)->default(0);
            $table->string('escrow_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for queries
            $table->index(['from_agent_did', 'status']);
            $table->index(['to_agent_did', 'status']);
            $table->index('created_at');

            // Foreign key to self for split payments
            $table->foreign('parent_payment_id')->references('id')->on('agent_payments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_payments');
    }
};
