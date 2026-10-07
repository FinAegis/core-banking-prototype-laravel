<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Double opt-in for newsletter subscribers: a subscriber stays `pending`
     * until the emailed confirmation link is opened. Only a SHA-256 hash of
     * the confirmation token is stored; `confirmation_sent_at` bounds re-sends.
     */
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->string('confirmation_token_hash', 64)->nullable()->after('confirmed_at');
            $table->dateTime('confirmation_sent_at')->nullable()->after('confirmation_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->dropColumn(['confirmation_token_hash', 'confirmation_sent_at']);
        });
    }
};
