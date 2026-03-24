<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table): void {
            $table->unique(['provider', 'provider_id'], 'sms_provider_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table): void {
            $table->dropUnique('sms_provider_message_unique');
        });
    }
};
