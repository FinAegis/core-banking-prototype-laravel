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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->uuid('account_uuid')->index();
            $table->string('type'); // deposit, withdrawal
            $table->string('status'); // pending, completed, failed
            $table->bigInteger('amount');
            $table->string('currency', 3);
            $table->string('reference')->unique();
            $table->string('external_reference')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_method_type')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_routing_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index(['account_uuid', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};