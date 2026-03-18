<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cardholders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('kyc_status')->default('pending'); // pending, in_review, verified, rejected
            $table->string('issuer_cardholder_id')->nullable()->index();
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country', 2)->nullable();
            $table->text('verification_data')->nullable(); // encrypted
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kyc_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardholders');
    }
};
