<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the non-custodial RAILGUN migration.
 *
 * Non-custodial wallets are created on-device; the backend stores only the
 * PUBLIC 0zk address (via POST /api/v1/privacy/wallet/register) and never holds
 * seed material. Make encrypted_mnemonic nullable so a registered wallet can
 * exist with no server-held seed. The legacy custodial path still sets it until
 * it is removed in Phase 3.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('railgun_wallets', function (Blueprint $table) {
            $table->text('encrypted_mnemonic')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('railgun_wallets', function (Blueprint $table) {
            $table->text('encrypted_mnemonic')->nullable(false)->change();
        });
    }
};
