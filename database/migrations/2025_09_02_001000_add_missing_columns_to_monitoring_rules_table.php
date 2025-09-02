<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('monitoring_rules', function (Blueprint $table) {
            // Add missing columns that factory expects
            if (! Schema::hasColumn('monitoring_rules', 'rule_type')) {
                $table->string('rule_type', 50)->nullable()->after('type');
            }
            if (! Schema::hasColumn('monitoring_rules', 'actions')) {
                $table->json('actions')->nullable()->after('conditions');
            }
            if (! Schema::hasColumn('monitoring_rules', 'metadata')) {
                $table->json('metadata')->nullable()->after('actions');
            }
            if (! Schema::hasColumn('monitoring_rules', 'tags')) {
                $table->json('tags')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('monitoring_rules', 'enabled')) {
                $table->boolean('enabled')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_rules', function (Blueprint $table) {
            $table->dropColumn(['rule_type', 'actions', 'metadata', 'tags', 'enabled']);
        });
    }
};
