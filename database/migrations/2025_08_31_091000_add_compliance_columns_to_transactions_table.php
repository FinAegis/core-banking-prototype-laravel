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
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('compliance_status', ['pending', 'reviewing', 'cleared', 'flagged'])->nullable()->after('meta_data');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable()->after('compliance_status');
            $table->decimal('risk_score', 5, 2)->nullable()->after('risk_level');
            $table->json('patterns_detected')->nullable()->after('risk_score');
            $table->timestamp('flagged_at')->nullable();
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('flag_reason')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('clear_reason')->nullable();

            $table->index('compliance_status');
            $table->index('risk_level');
            $table->index(['compliance_status', 'risk_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['compliance_status', 'risk_level']);
            $table->dropIndex(['risk_level']);
            $table->dropIndex(['compliance_status']);

            $table->dropForeign(['flagged_by']);
            $table->dropForeign(['cleared_by']);

            $table->dropColumn([
                'compliance_status',
                'risk_level',
                'risk_score',
                'patterns_detected',
                'flagged_at',
                'flagged_by',
                'flag_reason',
                'cleared_at',
                'cleared_by',
                'clear_reason',
            ]);
        });
    }
};
