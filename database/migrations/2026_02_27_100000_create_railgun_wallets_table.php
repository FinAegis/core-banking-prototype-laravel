<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('railgun_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('railgun_address', 128)->unique();
            $table->text('encrypted_mnemonic');
            $table->string('network', 20)->default('polygon');
            $table->unsignedBigInteger('last_scan_block')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('railgun_wallets');
    }
};
