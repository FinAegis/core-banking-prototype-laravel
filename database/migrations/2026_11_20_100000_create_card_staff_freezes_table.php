<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Records a card freeze that staff (StaffRoles::OPERATORS) or the card issuer
| placed. While a row exists, the card owner cannot lift the freeze; staff
| can. A freeze the owner placed has no row, so the owner can lift it again.
| Keyed by the issuer card token, because cards from the generic issuers
| (demo, Marqeta, Rain) have no local `cards` row.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('card_staff_freezes')) {
            return;
        }

        Schema::create('card_staff_freezes', function (Blueprint $table): void {
            $table->id();
            $table->string('issuer_card_token')->unique();
            $table->unsignedBigInteger('placed_by_user_id')->nullable();
            $table->string('source', 16)->default('staff'); // staff, issuer
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_staff_freezes');
    }
};
