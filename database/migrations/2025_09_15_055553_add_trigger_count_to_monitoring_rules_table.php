<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitoring_rules', function (Blueprint $table) {
            $table->unsignedInteger('trigger_count')->default(0)->after('false_positive_rate');
            $table->unsignedInteger('true_positives')->default(0)->after('trigger_count');
            $table->unsignedInteger('false_positives')->default(0)->after('true_positives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitoring_rules', function (Blueprint $table) {
            $table->dropColumn(['trigger_count', 'true_positives', 'false_positives']);
        });
    }
};
