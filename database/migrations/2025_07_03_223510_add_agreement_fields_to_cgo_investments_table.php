<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cgo_investments', function (Blueprint $table) {
            // Agreement fields
            if (!Schema::hasColumn('cgo_investments', 'agreement_path')) {
                $table->string('agreement_path')->nullable()->after('certificate_issued_at');
            }
            if (!Schema::hasColumn('cgo_investments', 'agreement_generated_at')) {
                $table->timestamp('agreement_generated_at')->nullable()->after('agreement_path');
            }
            if (!Schema::hasColumn('cgo_investments', 'agreement_signed_at')) {
                $table->timestamp('agreement_signed_at')->nullable()->after('agreement_generated_at');
            }
            if (!Schema::hasColumn('cgo_investments', 'certificate_path')) {
                $table->string('certificate_path')->nullable()->after('agreement_signed_at');
            }
            
            // Add indexes
            try {
                $table->index('agreement_generated_at');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cgo_investments', function (Blueprint $table) {
            try {
                $table->dropIndex(['agreement_generated_at']);
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            $table->dropColumn([
                'agreement_path',
                'agreement_generated_at',
                'agreement_signed_at',
                'certificate_path',
            ]);
        });
    }
};