<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CgoPricingRound;

class CgoPricingRoundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create first round (pre-launch)
        CgoPricingRound::create([
            'round_number' => 1,
            'share_price' => 10.00, // $10 per share
            'max_shares_available' => 10000, // 10,000 shares = 1% of total
            'shares_sold' => 0,
            'total_raised' => 0,
            'started_at' => now(),
            'is_active' => true,
        ]);
    }
}
