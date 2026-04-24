<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('account_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_review_account')->default(false)->index();
            $table->boolean('bypass_device_attestation')->default(false);
            $table->boolean('bypass_rate_limit')->default(false);
            $table->boolean('bypass_sanctions_screening')->default(false);
            $table->boolean('bypass_sms_otp')->default(false);
            $table->boolean('suppress_notifications')->default(false);
            $table->tinyInteger('kyc_override_level')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_flags');
    }
};
