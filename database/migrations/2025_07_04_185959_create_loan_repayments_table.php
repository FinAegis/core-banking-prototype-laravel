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
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('loan_id');
            $table->integer('payment_number');
            $table->decimal('amount', 19, 2);
            $table->decimal('principal_amount', 19, 2);
            $table->decimal('interest_amount', 19, 2);
            $table->decimal('remaining_balance', 19, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
            
            // $table->foreign('loan_id')->references('id')->on('loans');
            $table->unique(['loan_id', 'payment_number']);
            $table->index('loan_id');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
