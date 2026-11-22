<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| A Lepo card row exists before the card is opened at Lepo (the card opens
| after the first top-up), so the provider card id, last four digits and
| network are unknown at first. The provider card id stays unique.
| funding_account_id links the card to the provider funding account it is
| loaded from.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cards')) {
            return;
        }

        Schema::table('cards', function (Blueprint $table): void {
            $table->string('issuer_card_token')->nullable()->change();
            $table->string('last4', 4)->nullable()->change();
            $table->string('network')->nullable()->change();
        });

        if (! Schema::hasColumn('cards', 'funding_account_id')) {
            Schema::table('cards', function (Blueprint $table): void {
                $table->foreignUuid('funding_account_id')->nullable()->after('cardholder_id')
                    ->constrained('card_funding_accounts')->nullOnDelete();
                $table->index(['user_id', 'issuer', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cards')) {
            return;
        }

        if (Schema::hasColumn('cards', 'funding_account_id')) {
            Schema::table('cards', function (Blueprint $table): void {
                $table->dropIndex(['user_id', 'issuer', 'status']);
                $table->dropConstrainedForeignId('funding_account_id');
            });
        }

        // Rows without a provider card cannot go back to NOT NULL columns.
        $pending = DB::table('cards')->whereNull('issuer_card_token')->orWhereNull('last4')->orWhereNull('network')->exists();
        if (! $pending) {
            Schema::table('cards', function (Blueprint $table): void {
                $table->string('issuer_card_token')->nullable(false)->change();
                $table->string('last4', 4)->nullable(false)->change();
                $table->string('network')->nullable(false)->change();
            });
        }
    }
};
