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
        // Blockchain wallets projection table
        Schema::create('blockchain_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->enum('type', ['custodial', 'non-custodial', 'smart-contract']);
            $table->string('status')->default('active');
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });
        
        // Wallet addresses table
        Schema::create('wallet_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id');
            $table->string('chain');
            $table->string('address');
            $table->string('public_key');
            $table->string('derivation_path')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['chain', 'address']);
            $table->index(['wallet_id', 'chain']);
            $table->index('address');
        });
        
        // Blockchain transactions table
        Schema::create('blockchain_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id');
            $table->string('chain');
            $table->string('transaction_hash')->unique();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('amount');
            $table->string('asset')->default('native');
            $table->string('gas_used')->nullable();
            $table->string('gas_price')->nullable();
            $table->string('status')->default('pending');
            $table->integer('confirmations')->default(0);
            $table->bigInteger('block_number')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'status']);
            $table->index(['chain', 'block_number']);
            $table->index('from_address');
            $table->index('to_address');
        });
        
        // Wallet seeds (encrypted) table
        Schema::create('wallet_seeds', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->unique();
            $table->text('encrypted_seed');
            $table->string('storage_type')->default('database'); // database, hsm, etc.
            $table->timestamps();
        });
        
        // Token balances table
        Schema::create('token_balances', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id');
            $table->string('address');
            $table->string('chain');
            $table->string('token_address');
            $table->string('symbol');
            $table->string('name');
            $table->integer('decimals');
            $table->string('balance');
            $table->string('value_usd')->nullable();
            $table->timestamps();
            
            $table->unique(['address', 'chain', 'token_address']);
            $table->index(['wallet_id', 'chain']);
        });
        
        // Wallet backups table
        Schema::create('wallet_backups', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id');
            $table->string('backup_id')->unique();
            $table->string('backup_method');
            $table->text('encrypted_data');
            $table->string('checksum');
            $table->string('created_by');
            $table->timestamps();
            
            $table->index('wallet_id');
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
