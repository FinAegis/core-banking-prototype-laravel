<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| A deposit that no card can take (the user's only card is blocked) is still
| recorded, without a card, for an operator. conversion_requested_at and
| converted_at let the pipeline wait for a conversion Lepo finishes later and
| retry the steps before the card load without converting twice.
*/
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('card_topups', function (Blueprint $table): void {
            $table->uuid('card_id')->nullable()->change();
        });

        if (! Schema::hasColumn('card_topups', 'converted_at')) {
            Schema::table('card_topups', function (Blueprint $table): void {
                $table->timestamp('conversion_requested_at')->nullable()->after('step_started_at');
                $table->timestamp('converted_at')->nullable()->after('conversion_requested_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('card_topups', 'converted_at')) {
            Schema::table('card_topups', function (Blueprint $table): void {
                $table->dropColumn(['conversion_requested_at', 'converted_at']);
            });
        }
    }
};
