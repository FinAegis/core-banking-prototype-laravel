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
        Schema::table('regulatory_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('regulatory_reports', 'agent_id')) {
                $table->string('agent_id')->nullable()->after('report_type');
                $table->index('agent_id');
            }

            if (! Schema::hasColumn('regulatory_reports', 'regulatory_authority')) {
                $table->string('regulatory_authority')->nullable()->after('submission_reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regulatory_reports', function (Blueprint $table) {
            if (Schema::hasColumn('regulatory_reports', 'agent_id')) {
                $table->dropIndex(['agent_id']);
                $table->dropColumn('agent_id');
            }

            if (Schema::hasColumn('regulatory_reports', 'regulatory_authority')) {
                $table->dropColumn('regulatory_authority');
            }
        });
    }
};
