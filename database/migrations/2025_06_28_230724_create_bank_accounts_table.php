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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
            $table->string('bank_code', 50);
            $table->string('external_id'); // Bank's internal account ID
            $table->text('account_number'); // Encrypted
            $table->text('iban'); // Encrypted
            $table->string('swift', 20)->nullable();
            $table->string('currency', 3);
            $table->string('account_type', 50);
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->index(['user_uuid', 'bank_code']);
            $table->index(['status']);
            $table->unique(['bank_code', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};