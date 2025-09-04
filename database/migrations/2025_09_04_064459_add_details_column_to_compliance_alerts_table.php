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
        Schema::table('compliance_alerts', function (Blueprint $table) {
            // Add the details column that was missing from the original migration
            // This column stores additional details about the alert in JSON format
            $table->json('details')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_alerts', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
