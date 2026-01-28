<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for blockchain wallet tables.
 *
 * This migration runs in tenant database context, creating tables for
 * wallet management, addresses, blockchain transactions, and token balances.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blockchain_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->unique();
            $table->string('user_uuid')->index();
            $table->enum('type', ['custodial', 'non-custodial', 'smart-contract']);
            $table->string('status')->default('active')->index();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            // Security fields
            $table->boolean('mfa_enabled')->default(false);
            $table->boolean('withdrawal_locked')->default(false);
            $table->timestamp('last_activity_at')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'status']);
        });

        Schema::create('wallet_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->index();
            $table->string('chain');
            $table->string('address');
            $table->string('public_key');
            $table->string('derivation_path')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);

            // Security tracking
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable();

            $table->timestamps();

            $table->unique(['chain', 'address']);
            $table->index(['wallet_id', 'chain']);
            $table->index('address');
        });

        Schema::create('blockchain_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->index();
            $table->string('chain');
            $table->string('transaction_hash')->unique();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('amount');
            $table->string('asset')->default('native');
            $table->string('gas_used')->nullable();
            $table->string('gas_price')->nullable();
            $table->string('status')->default('pending')->index();
            $table->integer('confirmations')->default(0);
            $table->bigInteger('block_number')->nullable();
            $table->json('metadata')->nullable();

            // Security/Compliance fields
            $table->boolean('aml_screened')->default(false);
            $table->string('aml_status')->nullable();
            $table->timestamp('aml_screened_at')->nullable();

            // Audit fields
            $table->string('initiated_by')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
            $table->index(['chain', 'block_number']);
            $table->index('from_address');
            $table->index('to_address');
        });

        Schema::create('wallet_seeds', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->unique();
            // SECURITY: Encrypted seed phrase - use encrypted cast in model
            $table->text('encrypted_seed')->comment('Encrypted seed phrase');
            $table->string('storage_type')->default('database');
            $table->string('encryption_version')->default('v1');

            // Key rotation tracking
            $table->timestamp('last_rotated_at')->nullable();
            $table->string('rotated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('token_balances', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->index();
            $table->string('address');
            $table->string('chain');
            $table->string('token_address');
            $table->string('symbol');
            $table->string('name');
            $table->integer('decimals');
            $table->string('balance');
            $table->string('value_usd')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['address', 'chain', 'token_address']);
            $table->index(['wallet_id', 'chain']);
        });

        Schema::create('wallet_backups', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->index();
            $table->string('backup_id')->unique();
            $table->string('backup_method');
            // SECURITY: Encrypted backup data
            $table->text('encrypted_data')->comment('Encrypted backup data');
            $table->string('checksum');
            $table->string('created_by')->index();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_backups');
        Schema::dropIfExists('token_balances');
        Schema::dropIfExists('wallet_seeds');
        Schema::dropIfExists('blockchain_transactions');
        Schema::dropIfExists('wallet_addresses');
        Schema::dropIfExists('blockchain_wallets');
    }
};
