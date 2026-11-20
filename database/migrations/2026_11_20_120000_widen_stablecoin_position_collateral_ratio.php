<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| stablecoin_collateral_positions.collateral_ratio is recalculated from the
| collateral value and the debt. decimal(8,4) tops out at 9999.9999, so a
| well-collateralised position with a small debt overflowed it and the write
| failed in strict mode (500 on the positions read, which saves the ratio).
| decimal(20,4) keeps the four decimals and leaves room for any real ratio.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('stablecoin_collateral_positions')) {
            return;
        }

        Schema::table('stablecoin_collateral_positions', function (Blueprint $table) {
            $table->decimal('collateral_ratio', 20, 4)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stablecoin_collateral_positions')) {
            return;
        }

        Schema::table('stablecoin_collateral_positions', function (Blueprint $table) {
            $table->decimal('collateral_ratio', 8, 4)->change();
        });
    }
};
