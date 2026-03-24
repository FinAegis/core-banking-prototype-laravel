<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 50);
            $table->string('provider_id', 100)->index();
            $table->string('to', 20);
            $table->string('from', 20);
            $table->text('message');
            $table->unsignedSmallInteger('parts')->default(1);
            $table->string('status', 20)->default('pending');
            $table->string('price_usdc', 30);
            $table->string('country_code', 5);
            $table->string('payment_rail', 30)->nullable();
            $table->string('payment_id', 100)->nullable();
            $table->string('payment_receipt', 255)->nullable();
            $table->boolean('test_mode')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
