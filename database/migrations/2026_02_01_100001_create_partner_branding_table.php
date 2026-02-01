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
        Schema::create('partner_branding', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('partner_id');

            // Colors
            $table->string('primary_color', 7)->default('#1a365d');
            $table->string('secondary_color', 7)->default('#2b6cb0');
            $table->string('accent_color', 7)->nullable();
            $table->string('text_color', 7)->default('#1a202c');
            $table->string('background_color', 7)->default('#ffffff');

            // Logos
            $table->string('logo_url')->nullable();
            $table->string('logo_dark_url')->nullable();
            $table->string('favicon_url')->nullable();

            // Company Info
            $table->string('company_name');
            $table->string('tagline')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();

            // Legal
            $table->string('privacy_policy_url')->nullable();
            $table->string('terms_of_service_url')->nullable();

            // Custom Code (for enterprise)
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();

            // Widget Configuration
            $table->json('widget_config')->nullable();

            // Metadata
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_id')
                ->references('id')
                ->on('financial_institution_partners')
                ->onDelete('cascade');

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_branding');
    }
};
