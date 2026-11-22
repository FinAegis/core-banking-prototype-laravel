<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Provider-neutral card data (Lepo first, FinCard second).
|
| cardholders.provider records which card provider a cardholder belongs to;
| every later action reads it from the row, never from configuration, so
| changing the default provider does not move existing users. Cardholders
| created through FinCard (the only provider so far with an issuer id) are
| backfilled as 'fincard'.
|
| card_funding_accounts and card_deposit_addresses hold the provider-side
| funding account (the balance cards are loaded from) and its deposit
| addresses for every provider; FinCard's own tables stay until it moves.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cardholders') && ! Schema::hasColumn('cardholders', 'provider')) {
            Schema::table('cardholders', function (Blueprint $table): void {
                $table->string('provider', 32)->nullable()->after('user_id')->index();
            });

            DB::table('cardholders')->whereNotNull('issuer_cardholder_id')->update(['provider' => 'fincard']);

            // One cardholder per user and provider (rows without a provider are
            // older, provider-less rows; NULLs never collide in a unique index).
            Schema::table('cardholders', function (Blueprint $table): void {
                $table->unique(['user_id', 'provider'], 'cardholders_user_provider_unique');
            });
        }

        if (! Schema::hasTable('card_funding_accounts')) {
            Schema::create('card_funding_accounts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('cardholder_id')->constrained('cardholders')->cascadeOnDelete();
                $table->string('provider', 32);
                $table->string('provider_account_id')->nullable();
                $table->string('currency', 8)->default('USD');
                $table->string('status', 20)->default('pending'); // pending, active, suspended
                $table->bigInteger('balance_cents')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
            });
        }

        if (! Schema::hasTable('card_deposit_addresses')) {
            Schema::create('card_deposit_addresses', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('funding_account_id')->constrained('card_funding_accounts')->cascadeOnDelete();
                $table->string('provider', 32);
                $table->string('network', 32);
                $table->string('asset', 16);
                $table->string('address', 128);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['funding_account_id', 'network', 'asset']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_deposit_addresses');
        Schema::dropIfExists('card_funding_accounts');

        if (Schema::hasTable('cardholders') && Schema::hasColumn('cardholders', 'provider')) {
            Schema::table('cardholders', function (Blueprint $table): void {
                $table->dropUnique('cardholders_user_provider_unique');
                $table->dropIndex(['provider']);
                $table->dropColumn('provider');
            });
        }
    }
};
