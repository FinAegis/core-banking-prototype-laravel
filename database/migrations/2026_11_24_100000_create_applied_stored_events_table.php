<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The stored events each balance projector has already applied. The asset
| projectors claim a stored event here in the same transaction that moves the
| money, so a replay or a second delivery does not book it again. The table
| lives next to account_balances. Tenant copy: tenant/0001_01_01_000018.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('applied_stored_events')) {
            return;
        }

        Schema::create('applied_stored_events', function (Blueprint $table): void {
            $table->id();
            $table->string('projection', 100);
            $table->string('stored_event_id', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['projection', 'stored_event_id'], 'applied_stored_events_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applied_stored_events');
    }
};
