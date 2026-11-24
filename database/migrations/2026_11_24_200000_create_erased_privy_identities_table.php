<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Sign-in identities of deleted accounts, kept only as a keyed hash with the
| time of the deletion. An app that still holds a session from before the
| deletion cannot open a new account with it (see ErasedPrivyIdentities).
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('erased_privy_identities')) {
            return;
        }

        Schema::create('erased_privy_identities', function (Blueprint $table): void {
            $table->id();
            $table->string('identity_hash', 64)->unique();
            $table->timestamp('erased_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erased_privy_identities');
    }
};
