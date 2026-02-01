<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('partner_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('partner_id');
            $table->string('invoice_number')->unique();

            // Period
            $table->date('period_start');
            $table->date('period_end');
            $table->string('billing_cycle'); // monthly, quarterly, annually

            // Status
            $table->string('status')->default('draft'); // draft, pending, paid, overdue, cancelled, refunded

            // Tier at time of invoice
            $table->string('tier');

            // Base charges
            $table->decimal('base_amount_usd', 10, 2);
            $table->decimal('discount_amount_usd', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();

            // Usage charges
            $table->unsignedBigInteger('total_api_calls');
            $table->unsignedBigInteger('included_api_calls');
            $table->unsignedBigInteger('overage_api_calls')->default(0);
            $table->decimal('overage_amount_usd', 10, 2)->default(0);

            // Additional charges
            $table->json('line_items')->nullable();
            $table->decimal('additional_charges_usd', 10, 2)->default(0);

            // Totals
            $table->decimal('subtotal_usd', 10, 2);
            $table->decimal('tax_amount_usd', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('total_amount_usd', 10, 2);

            // Currency conversion (if applicable)
            $table->string('display_currency', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->default(1.0);
            $table->decimal('total_amount_display', 10, 2);

            // Payment
            $table->string('payment_method')->nullable(); // card, bank_transfer, invoice
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('due_date');

            // PDF
            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_id')
                ->references('id')
                ->on('financial_institution_partners')
                ->onDelete('cascade');

            $table->index('status');
            $table->index('due_date');
            $table->index(['partner_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_invoices');
    }
};
