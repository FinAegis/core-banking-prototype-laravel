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
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_uuid')->constrained()->nullOnDelete();
            $table->index(['team_id', 'user_uuid']);
        });
        
        // Also add team_id to transactions for isolation
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('aggregate_uuid')->constrained()->nullOnDelete();
            $table->index('team_id');
        });
        
        // Add team_id to fraud_cases for isolation
        Schema::table('fraud_cases', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('team_id');
        });
        
        // Add team_id to regulatory_reports for isolation
        Schema::table('regulatory_reports', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regulatory_reports', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
        
        Schema::table('fraud_cases', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
        
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};