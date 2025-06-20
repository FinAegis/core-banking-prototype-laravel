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
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
            $table->enum('document_type', [
                'passport',
                'national_id',
                'drivers_license',
                'residence_permit',
                'utility_bill',
                'bank_statement',
                'selfie',
                'proof_of_income',
                'other'
            ]);
            $table->enum('status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->string('file_path')->nullable();
            $table->string('file_hash')->nullable()->comment('SHA-256 hash for integrity');
            $table->json('metadata')->nullable()->comment('Additional document metadata');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('verified_by')->nullable()->comment('Admin user who verified');
            $table->timestamps();
            
            // Indexes
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->index('document_type');
            $table->index('status');
            $table->index(['user_uuid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
