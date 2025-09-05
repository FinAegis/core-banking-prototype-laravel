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
        Schema::table('compliance_cases', function (Blueprint $table) {
            // Add case_number column if it doesn't exist
            if (! Schema::hasColumn('compliance_cases', 'case_number')) {
                $table->string('case_number')->unique()->index()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_cases', function (Blueprint $table) {
            $table->dropColumn('case_number');
        });
    }
};
