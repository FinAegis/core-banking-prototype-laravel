<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the delegated_proof_jobs table for server-side ZK proof generation.
 *
 * Tracks proof generation requests from mobile devices that cannot
 * perform client-side proving due to resource constraints.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delegated_proof_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('proof_type', 30)->comment('Type of proof: shield_1_1, unshield_2_1, transfer_2_2, proof_of_innocence');
            $table->string('network', 20);
            $table->json('public_inputs')->comment('Public inputs for the proof');
            $table->text('encrypted_private_inputs')->comment('Encrypted private inputs (client-encrypted)');
            $table->enum('status', ['queued', 'proving', 'completed', 'failed'])->default('queued');
            $table->unsignedTinyInteger('progress')->default(0)->comment('Progress percentage 0-100');
            $table->text('proof')->nullable()->comment('Generated proof (hex-encoded)');
            $table->text('error')->nullable()->comment('Error message if failed');
            $table->unsignedInteger('estimated_seconds')->nullable()->comment('Estimated generation time');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('proof_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegated_proof_jobs');
    }
};
