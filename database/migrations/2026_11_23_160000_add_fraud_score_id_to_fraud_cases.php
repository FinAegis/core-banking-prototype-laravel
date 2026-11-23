<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Links a fraud case to the fraud score that opened it. The case service and
| the regulatory SAR read the score through this link; it never existed, so
| both failed.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('fraud_cases') || Schema::hasColumn('fraud_cases', 'fraud_score_id')) {
            return;
        }

        Schema::table('fraud_cases', function (Blueprint $table): void {
            $table->uuid('fraud_score_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fraud_cases') && Schema::hasColumn('fraud_cases', 'fraud_score_id')) {
            Schema::table('fraud_cases', function (Blueprint $table): void {
                $table->dropIndex(['fraud_score_id']);
                $table->dropColumn('fraud_score_id');
            });
        }
    }
};
