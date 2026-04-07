<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ramp_sessions', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->after('provider_session_id');
            $table->string('stripe_client_secret')->nullable()->after('stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('ramp_sessions', function (Blueprint $table) {
            $table->dropColumn(['stripe_session_id', 'stripe_client_secret']);
        });
    }
};
