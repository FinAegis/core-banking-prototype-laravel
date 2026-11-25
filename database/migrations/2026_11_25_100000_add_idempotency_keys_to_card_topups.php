<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One idempotency key per money call of a top-up: the conversion and the card
| load. Each key is generated once and saved before its call, so a call sent
| again for the same top-up carries the same key. The key is a best-effort
| extra; unclear results are still reconciled by an operator.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('card_topups', 'conversion_idempotency_key')) {
            return;
        }

        Schema::table('card_topups', function (Blueprint $table): void {
            $table->uuid('conversion_idempotency_key')->nullable()->after('provider_load_id');
            $table->uuid('load_idempotency_key')->nullable()->after('conversion_idempotency_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('card_topups', 'conversion_idempotency_key')) {
            return;
        }

        Schema::table('card_topups', function (Blueprint $table): void {
            $table->dropColumn(['conversion_idempotency_key', 'load_idempotency_key']);
        });
    }
};
