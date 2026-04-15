<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('error_code')->nullable()->after('status');
            $table->string('mcc', 6)->nullable()->after('error_code');
            $table->string('mnc', 6)->nullable()->after('mcc');
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table): void {
            $table->dropColumn(['error_code', 'mcc', 'mnc']);
        });
    }
};
