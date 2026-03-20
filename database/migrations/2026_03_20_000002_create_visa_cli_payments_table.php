<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('visa_cli_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->comment('Agent or gateway identifier');
            $table->unsignedBigInteger('invoice_id')->nullable()->comment('FK to partner_invoices');
            $table->string('url')->comment('Payment target URL');
            $table->integer('amount_cents')->comment('Payment amount in USD cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending')->comment('Payment status');
            $table->string('card_identifier')->nullable()->comment('Card used for payment');
            $table->string('payment_reference')->nullable()->comment('External payment reference');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('agent_id');
            $table->index('invoice_id');
            $table->index('payment_reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_cli_payments');
    }
};
