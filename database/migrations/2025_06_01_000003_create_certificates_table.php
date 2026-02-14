<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('issuer_type');
            $table->string('status')->default('pending');
            $table->string('credential_type')->nullable();
            $table->json('claims')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('status');
            $table->index('issuer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
