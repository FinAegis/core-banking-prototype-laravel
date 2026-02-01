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
        Schema::table('financial_institution_partners', function (Blueprint $table) {
            $table->string('tier')->default('starter')->after('status');
            $table->string('custom_domain')->nullable()->after('tier');
            $table->boolean('white_label_enabled')->default(false)->after('custom_domain');

            $table->index('tier');
            $table->index('white_label_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_institution_partners', function (Blueprint $table) {
            $table->dropIndex(['tier']);
            $table->dropIndex(['white_label_enabled']);

            $table->dropColumn(['tier', 'custom_domain', 'white_label_enabled']);
        });
    }
};
