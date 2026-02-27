<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shielded_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('railgun_address', 128);
            $table->string('token', 20);
            $table->string('network', 20);
            $table->string('balance', 78)->default('0');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('railgun_address');
            $table->unique(['user_id', 'token', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shielded_balances');
    }
};
