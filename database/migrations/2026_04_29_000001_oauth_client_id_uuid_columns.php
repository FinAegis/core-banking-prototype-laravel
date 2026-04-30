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
 *
 * MariaDB rejects a direct ->change() from bigint unsigned to uuid
 * ("Cannot cast 'bigint unsigned' as 'uuid'"), so we drop and recreate the
 * column. None of the three columns carry an index in the original schema.
 */
return new class () extends Migration {
    /** @var list<string> */
    private array $tables = [
        'oauth_auth_codes',
        'oauth_access_tokens',
        'oauth_personal_access_clients',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('client_id');
            });
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('client_id')->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('client_id');
            });
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->after('id');
            });
        }
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
