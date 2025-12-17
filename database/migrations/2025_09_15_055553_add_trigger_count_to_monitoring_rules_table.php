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
            if (! Schema::hasColumn('monitoring_rules', 'trigger_count')) {
                $table->unsignedInteger('trigger_count')->default(0);
            }
        });

        Schema::table('monitoring_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_rules', 'true_positives')) {
                $table->unsignedInteger('true_positives')->default(0);
            }
        });

        Schema::table('monitoring_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_rules', 'false_positives')) {
                $table->unsignedInteger('false_positives')->default(0);
            }
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
