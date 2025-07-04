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
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('is_tradeable')->default(false)->after('is_active');
            $table->index('is_tradeable');
        });
        
        // Mark specific assets as tradeable
        DB::table('assets')->whereIn('code', ['BTC', 'ETH', 'EUR', 'USD', 'GBP', 'GCU'])->update([
            'is_tradeable' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['is_tradeable']);
            $table->dropColumn('is_tradeable');
        });
    }
};
