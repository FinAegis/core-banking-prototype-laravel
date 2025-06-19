<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add CHF and JPY assets for currency basket
        DB::table('assets')->insert([
            [
                'code' => 'CHF',
                'name' => 'Swiss Franc',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'metadata' => json_encode(['symbol' => 'Fr.', 'iso_code' => 'CHF']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'type' => 'fiat',
                'precision' => 0, // JPY doesn't use decimal places
                'is_active' => true,
                'metadata' => json_encode(['symbol' => '¥', 'iso_code' => 'JPY']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('assets')->whereIn('code', ['CHF', 'JPY'])->delete();
    }
};