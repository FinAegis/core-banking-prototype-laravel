<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_institution_partners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('partner_code')->unique(); // FIP-XXXXX
            $table->uuid('application_id');
            
            // Partner Information
            $table->string('institution_name');
            $table->string('legal_name');
            $table->string('institution_type');
            $table->string('country', 2);
            $table->string('status')->default('active'); // active, suspended, terminated
            
            // API Credentials
            $table->string('api_client_id')->unique();
            $table->text('api_client_secret'); // Encrypted
            $table->text('webhook_secret')->nullable(); // Encrypted
            $table->json('api_permissions')->nullable();
            $table->json('allowed_ip_addresses')->nullable();
            
            // Integration Settings
            $table->boolean('sandbox_enabled')->default(true);
            $table->boolean('production_enabled')->default(false);
            $table->json('enabled_features')->nullable();
            $table->json('disabled_features')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->integer('rate_limit_per_day')->default(10000);
            
            // Operational Limits
            $table->decimal('max_transaction_amount', 20, 2)->nullable();
            $table->decimal('daily_transaction_limit', 20, 2)->nullable();
            $table->decimal('monthly_transaction_limit', 20, 2)->nullable();
            $table->integer('max_accounts_per_user')->default(10);
            $table->json('allowed_currencies')->nullable();
            $table->json('allowed_countries')->nullable();
            
            // Fee Structure
            $table->json('fee_structure');
            $table->decimal('revenue_share_percentage', 5, 2)->default(0);
            $table->string('billing_cycle')->default('monthly'); // monthly, quarterly, annually
            $table->date('next_billing_date')->nullable();
            
            // Compliance
            $table->string('risk_rating');
            $table->decimal('risk_score', 5, 2);
            $table->json('compliance_requirements')->nullable();
            $table->date('last_audit_date')->nullable();
            $table->date('next_audit_date')->nullable();
            
            // Contact Information
            $table->json('primary_contact');
            $table->json('technical_contact')->nullable();
            $table->json('compliance_contact')->nullable();
            $table->json('billing_contact')->nullable();
            
            // Performance Metrics
            $table->integer('total_accounts')->default(0);
            $table->integer('active_accounts')->default(0);
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_volume', 20, 2)->default(0);
            $table->timestamp('last_activity_at')->nullable();
            
            // Webhooks
            $table->json('webhook_endpoints')->nullable();
            $table->boolean('webhook_active')->default(true);
            $table->integer('webhook_retry_count')->default(3);
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->text('termination_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->foreign('application_id')->references('id')->on('financial_institution_applications');
            $table->index('status');
            $table->index('institution_type');
            $table->index('country');
            $table->index('risk_rating');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_institution_partners');
    }
};