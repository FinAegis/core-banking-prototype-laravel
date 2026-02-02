<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the privacy_merkle_states table for storing Merkle tree state per network.
 *
 * This table caches the current Merkle root and tree metadata for each supported
 * privacy pool network. Used for mobile client synchronization.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('privacy_merkle_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('network', 20)->unique();
            $table->string('root', 66)->comment('Current Merkle root (0x + 64 hex chars)');
            $table->unsignedBigInteger('leaf_count')->default(0);
            $table->unsignedInteger('tree_depth')->default(32);
            $table->unsignedBigInteger('block_number')->comment('Block number at last sync');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->index('network');
            $table->index('synced_at');
        });

        // Table for tracking known commitments (for demo/testing purposes)
        Schema::create('privacy_commitments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('commitment', 66)->comment('32-byte commitment hash with 0x prefix');
            $table->string('network', 20);
            $table->unsignedBigInteger('leaf_index');
            $table->string('nullifier', 66)->nullable()->comment('Nullifier hash if spent');
            $table->boolean('is_spent')->default(false);
            $table->timestamp('shielded_at')->nullable();
            $table->timestamp('spent_at')->nullable();
            $table->timestamps();

            $table->unique(['commitment', 'network']);
            $table->index(['user_id', 'network']);
            $table->index(['network', 'is_spent']);
            $table->index('nullifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_commitments');
        Schema::dropIfExists('privacy_merkle_states');
    }
};
