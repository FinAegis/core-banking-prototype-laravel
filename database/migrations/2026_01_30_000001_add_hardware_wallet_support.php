<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Add signer_type to blockchain_wallets table
        if (Schema::hasTable('blockchain_wallets') && ! Schema::hasColumn('blockchain_wallets', 'signer_type')) {
            Schema::table('blockchain_wallets', function (Blueprint $table): void {
                $table->string('signer_type', 50)
                    ->default('internal')
                    ->after('status')
                    ->comment('Type of signer: internal, hardware_ledger, hardware_trezor, multi_sig');
            });
        }

        // Hardware wallet associations - links hardware wallets to users and addresses
        Schema::create('hardware_wallet_associations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_type', 50)->comment('ledger_nano_s, ledger_nano_x, trezor_one, trezor_model_t');
            $table->string('device_id')->comment('Unique device identifier');
            $table->string('device_label')->nullable()->comment('User-friendly device name');
            $table->string('firmware_version', 50)->nullable();
            $table->string('public_key')->comment('Master public key from device');
            $table->string('address')->nullable()->comment('Primary address derived from public key');
            $table->string('chain', 50)->comment('Blockchain chain: ethereum, bitcoin, polygon, bsc');
            $table->string('derivation_path')->comment('BIP44 derivation path');
            $table->json('supported_chains')->comment('List of chains this device supports');
            $table->json('metadata')->nullable()->comment('Additional device metadata');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['device_id']);
            $table->index(['address', 'chain']);
            $table->unique(['user_id', 'device_id', 'chain']);
        });

        // Pending signing requests - tracks async signing requests
        Schema::create('pending_signing_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('association_id');
            $table->string('status', 50)->default('pending')
                ->comment('pending, awaiting_device, signing, completed, failed, expired, cancelled');
            $table->json('transaction_data')->comment('Transaction details to be signed');
            $table->text('raw_data_to_sign')->comment('RLP encoded or serialized data for signing');
            $table->string('chain', 50)->comment('Blockchain chain');
            $table->string('signature')->nullable()->comment('Signature from hardware device');
            $table->string('public_key')->nullable()->comment('Public key used for signing');
            $table->string('signed_transaction_hash')->nullable()->comment('Hash of signed transaction');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('association_id')
                ->references('id')
                ->on('hardware_wallet_associations')
                ->cascadeOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['association_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['created_at']);
        });

        // Add index for signer_type lookups
        if (Schema::hasTable('blockchain_wallets') && Schema::hasColumn('blockchain_wallets', 'signer_type')) {
            Schema::table('blockchain_wallets', function (Blueprint $table): void {
                $table->index('signer_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_signing_requests');
        Schema::dropIfExists('hardware_wallet_associations');

        if (Schema::hasTable('blockchain_wallets') && Schema::hasColumn('blockchain_wallets', 'signer_type')) {
            Schema::table('blockchain_wallets', function (Blueprint $table): void {
                $table->dropIndex(['signer_type']);
                $table->dropColumn('signer_type');
            });
        }
    }
};
