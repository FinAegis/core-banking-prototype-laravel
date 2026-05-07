<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile sends Intl.DateTimeFormat().resolvedOptions().timeZone on every
 * /api/v1/auth/privy-login call. We persist it so server-side daily
 * resets (spending limits, MCP grant projections) match what the user
 * sees in their wallet. IANA TZ names like "Europe/Vilnius" max out
 * around 32 chars; 64 is generous + future-proof.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('privy_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
