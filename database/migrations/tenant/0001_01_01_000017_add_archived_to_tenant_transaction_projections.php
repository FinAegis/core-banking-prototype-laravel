<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The batch job's "archive old transactions" step marks transactions older
| than seven years as archived, but the column never existed, so the step
| always failed. Central copy: 2026_11_23_150000.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('transaction_projections') || Schema::hasColumn('transaction_projections', 'archived')) {
            return;
        }

        Schema::table('transaction_projections', function (Blueprint $table): void {
            $table->boolean('archived')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('transaction_projections') && Schema::hasColumn('transaction_projections', 'archived')) {
            Schema::table('transaction_projections', function (Blueprint $table): void {
                $table->dropColumn('archived');
            });
        }
    }
};
