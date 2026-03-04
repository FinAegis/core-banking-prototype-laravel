<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('free_tx_until')->nullable()->after('mobile_preferences');
            $table->unsignedInteger('sponsored_tx_used')->default(0)->after('free_tx_until');
            $table->unsignedInteger('sponsored_tx_limit')->default(0)->after('sponsored_tx_used');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['free_tx_until', 'sponsored_tx_used', 'sponsored_tx_limit']);
        });
    }
};
