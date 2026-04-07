<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\Schema;

trait CreatesSolanaTestTables
{
    protected function createSolanaTestTables(): void
    {
        if (! Schema::hasTable('blockchain_addresses')) {
            Schema::create('blockchain_addresses', function ($table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('user_uuid')->index();
                $table->string('chain');
                $table->string('address');
                $table->text('public_key');
                $table->string('derivation_path')->nullable();
                $table->string('label')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['chain', 'address']);
            });
        }

        if (! Schema::hasTable('blockchain_address_transactions')) {
            Schema::create('blockchain_address_transactions', function ($table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('address_uuid')->index();
                $table->string('tx_hash');
                $table->string('type');
                $table->decimal('amount', 36, 18);
                $table->decimal('fee', 36, 18)->default(0);
                $table->string('from_address');
                $table->string('to_address');
                $table->string('chain');
                $table->unique(['tx_hash', 'chain'], 'uq_tx_hash_chain');
                $table->string('status')->default('pending');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activity_feed_items')) {
            Schema::create('activity_feed_items', function ($table): void {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('activity_type', 30);
                $table->string('merchant_name')->nullable();
                $table->string('merchant_icon_url')->nullable();
                $table->decimal('amount', 20, 8);
                $table->string('asset', 10);
                $table->string('network', 20)->nullable();
                $table->string('status', 20)->default('pending');
                $table->boolean('protected')->default(false);
                $table->string('reference_type')->nullable();
                $table->uuid('reference_id')->nullable();
                $table->string('from_address', 64)->nullable();
                $table->string('to_address', 64)->nullable();
                $table->dateTime('occurred_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function dropSolanaTestTables(): void
    {
        Schema::dropIfExists('activity_feed_items');
        Schema::dropIfExists('blockchain_address_transactions');
        Schema::dropIfExists('blockchain_addresses');
    }
}
