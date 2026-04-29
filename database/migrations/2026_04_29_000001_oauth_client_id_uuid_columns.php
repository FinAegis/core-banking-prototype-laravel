<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the client_id foreign-key columns to UUID to match the
 * oauth_clients.id type adopted in 2026_04_28_000004 (Passport v13).
 *
 * Without this, MariaDB silently coerces UUIDs like "019ddaae-8521-..." into
 * a leading-digit integer and emits "Data truncated for column 'client_id'"
 * during the OAuth authorization-code grant. There are no in-flight rows to
 * preserve — OAuth was functionally inert prior to v13 (see the recreation
 * comment in 2026_04_28_000004).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->uuid('client_id')->change();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->uuid('client_id')->change();
        });

        Schema::table('oauth_personal_access_clients', function (Blueprint $table) {
            $table->uuid('client_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->change();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->change();
        });

        Schema::table('oauth_personal_access_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->change();
        });
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
