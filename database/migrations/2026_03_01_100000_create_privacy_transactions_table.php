<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('privacy_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tx_hash', 66)->nullable()->index();
            $table->string('operation', 20); // shield|unshield|transfer
            $table->string('token', 20);
            $table->string('amount');
            $table->string('network', 20)->index();
            $table->string('to_address');
            $table->text('calldata'); // encrypted via model cast
            $table->string('value')->nullable();
            $table->string('gas_estimate')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('recipient')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_transactions');
    }
};
