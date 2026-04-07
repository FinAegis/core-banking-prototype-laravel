<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('blockchain_address_transactions', function (Blueprint $table) {
            $table->dropIndex(['tx_hash']);
            $table->unique(['tx_hash', 'chain'], 'uq_tx_hash_chain');
        });
    }

    public function down(): void
    {
        Schema::table('blockchain_address_transactions', function (Blueprint $table) {
            $table->dropUnique('uq_tx_hash_chain');
            $table->index('tx_hash');
        });
    }
};
