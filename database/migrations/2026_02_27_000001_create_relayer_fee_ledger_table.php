<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('relayer_fee_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_address', 42);
            $table->string('token', 10);
            $table->decimal('amount', 18, 6);
            $table->string('network', 20);
            $table->string('type', 30);
            $table->string('user_op_hash', 66)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_address');
            $table->index(['user_address', 'network', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relayer_fee_ledger');
    }
};
