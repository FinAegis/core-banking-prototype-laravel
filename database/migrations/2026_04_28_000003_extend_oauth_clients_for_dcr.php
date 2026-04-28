<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('client_logo_url', 512)->nullable()->after('redirect');
            $table->string('client_terms_url', 512)->nullable()->after('client_logo_url');
            $table->string('client_privacy_url', 512)->nullable()->after('client_terms_url');
            $table->string('dcr_metadata_uri', 512)->nullable()->after('client_privacy_url');
            $table->enum('registration_method', ['dcr', 'manual', 'passport_native'])
                ->default('passport_native')
                ->after('dcr_metadata_uri');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['client_logo_url', 'client_terms_url', 'client_privacy_url', 'dcr_metadata_uri', 'registration_method']);
        });
    }
};
