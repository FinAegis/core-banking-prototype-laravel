<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Money controls on user API keys. All three columns are nullable: a key
 * without its own limit uses the configured default
 * (rate_limiting.api_key_money), and a key without pinned accounts may move
 * money from any account its owner owns. Limits are major units with two
 * decimals, matching the hundredths the limit check counts in.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->decimal('transfer_limit', 20, 2)->nullable()->after('allowed_ips');
            $table->decimal('daily_limit', 20, 2)->nullable()->after('transfer_limit');
            $table->json('allowed_account_uuids')->nullable()->after('daily_limit');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['transfer_limit', 'daily_limit', 'allowed_account_uuids']);
        });
    }
};
