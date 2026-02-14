<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('defi_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('protocol');
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('chain');
            $table->string('asset');
            $table->decimal('amount', 36, 18);
            $table->decimal('value_usd', 20, 2)->nullable();
            $table->decimal('apy', 10, 4)->nullable();
            $table->decimal('health_factor', 10, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('protocol');
            $table->index('status');
            $table->index('chain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defi_positions');
    }
};
