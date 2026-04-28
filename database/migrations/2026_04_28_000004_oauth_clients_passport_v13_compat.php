<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapts oauth_clients to the Passport v13 schema (UUID id, polymorphic owner,
 * redirect_uris/grant_types text columns) while preserving the v7.10.x DCR
 * extension columns added by 2026_04_28_000003.
 *
 * The original 2024 migration provisioned a Passport v12 schema (bigint id,
 * user_id, redirect text, personal_access_client/password_client bools). After
 * the Passport v13 upgrade, that schema no longer matched the framework's
 * expectations, so OAuth has been functionally inert — there are no in-flight
 * client rows to preserve. This migration drops and recreates the table to
 * land cleanly on the v13 contract; the recreation includes the DCR columns
 * so we don't rely on the order of the prior add-column migrations.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('oauth_clients');

        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('revoked');
            $table->timestamps();

            $table->string('client_logo_url', 512)->nullable();
            $table->string('client_terms_url', 512)->nullable();
            $table->string('client_privacy_url', 512)->nullable();
            $table->string('dcr_metadata_uri', 512)->nullable();
            $table->enum('registration_method', ['dcr', 'manual', 'passport_native'])
                ->default('passport_native');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');

        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('secret', 100)->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect');
            $table->boolean('personal_access_client');
            $table->boolean('password_client');
            $table->boolean('revoked');
            $table->timestamps();

            $table->string('client_logo_url', 512)->nullable()->after('redirect');
            $table->string('client_terms_url', 512)->nullable()->after('client_logo_url');
            $table->string('client_privacy_url', 512)->nullable()->after('client_terms_url');
            $table->string('dcr_metadata_uri', 512)->nullable()->after('client_privacy_url');
            $table->enum('registration_method', ['dcr', 'manual', 'passport_native'])
                ->default('passport_native')
                ->after('dcr_metadata_uri');
        });
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
