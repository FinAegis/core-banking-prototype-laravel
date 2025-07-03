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
        Schema::table('cgo_pricing_rounds', function (Blueprint $table) {
            if (!Schema::hasColumn('cgo_pricing_rounds', 'name')) {
                $table->string('name')->nullable()->after('round_number');
            }
            if (!Schema::hasColumn('cgo_pricing_rounds', 'pre_money_valuation')) {
                $table->decimal('pre_money_valuation', 15, 2)->nullable()->after('total_raised');
            }
            if (!Schema::hasColumn('cgo_pricing_rounds', 'post_money_valuation')) {
                $table->decimal('post_money_valuation', 15, 2)->nullable()->after('pre_money_valuation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cgo_pricing_rounds', function (Blueprint $table) {
            $table->dropColumn(['name', 'pre_money_valuation', 'post_money_valuation']);
        });
    }
};